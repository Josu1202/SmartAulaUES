<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\NfcCredential;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\ClosureSnapshot;
use App\Support\LocalDatabase;
use Carbon\CarbonImmutable;
use Database\Seeders\ExplorationSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LocalExplorationTest extends TestCase
{
    public static function dates(): array
    {
        return [['2027-01-03T06:02:00Z'], ['2028-12-30T23:58:00Z']];
    }

    #[DataProvider('dates')]
    public function test_relative_scenario_has_valid_results_and_repetition_preserves_every_change(string $date): void
    {
        $anchor = CarbonImmutable::parse($date);
        $this->travelTo($anchor);
        config(['smartaulaues.exploration_password_hash' => Hash::make('Local-only-password-2026!')]);
        $this->seed(ExplorationSeeder::class);
        $this->assertSame($anchor->toIso8601String(), now()->toIso8601String());
        $this->assertDatabaseCount('users', 52);
        $this->assertSame(7, User::where('role', 'teacher')->count());
        $this->assertSame(44, User::where('role', 'student')->count());
        $this->assertSame(3, User::where('graduated', true)->count());
        $this->assertSame(1, User::where('must_change_password', true)->count());
        $this->assertSame(2, User::where('active', false)->count());
        $this->assertSame([1, 2, 3, 4, 5], User::where('role', 'student')->where('graduated', false)->distinct()->orderBy('study_year')->pluck('study_year')->all());
        $this->assertFalse(User::where('email', 'not like', '%@example.test')->exists());
        $this->assertFalse(User::where('name', 'ilike', '%test%')->orWhere('name', 'ilike', '%a05%')->exists());
        $this->assertSame(['approved', 'pending', 'rejected'], AbsenceRequest::distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(2, AbsenceRequest::where('status', 'pending')->count());
        $this->assertTrue(Offering::where('active', false)->exists());
        $this->assertTrue(NfcCredential::where('state', 'pending')->where('pending_expires_at', '<', now())->exists());
        $this->assertSame(['active', 'pending', 'revoked'], NfcCredential::distinct()->orderBy('state')->pluck('state')->all());
        $this->assertSame(['cancelled', 'completed', 'in_progress', 'scheduled', 'unstarted'], ClassSession::distinct()->orderBy('state')->pluck('state')->map(fn ($state) => $state->value)->all());
        $this->assertFalse(AuditLog::where('created_at', '>', $anchor)->exists());
        foreach (ClassSession::with('offering.cycle')->get() as $session) {
            $this->assertLessThanOrEqual($session->starts_at, $session->created_at);
            $this->assertLessThanOrEqual($session->created_at, $session->offering->created_at);
            $this->assertLessThanOrEqual($session->offering->created_at, $session->offering->cycle->created_at);
            if ($session->started_at) {
                $this->assertLessThanOrEqual($session->started_at, $session->created_at);
            }
        }
        $current = AcademicCycle::where('code', 'like', 'Ciclo actual%')->sole();
        $this->assertSame($anchor->timezone('America/El_Salvador')->subDays(90)->toDateString(), $current->starts_on->toDateString());
        $group = $current->offerings()->orderBy('id')->first();
        $period = $group->periods()->where('number', 2)->sole();
        $admin = User::where('role', 'coordinator')->sole();
        $percentages = app(AttendanceSummary::class)->forOffering($admin, $group, $period->id)['rows']->getCollection()->pluck('calculation.percentage')->all();
        foreach (['100.00', '75.00', '50.00'] as $percentage) {
            $this->assertContains($percentage, $percentages);
        }
        foreach (ReportVersion::with('period')->get() as $report) {
            $this->assertTrue(app(ClosureSnapshot::class)->valid($report, $report->period));
        }
        $this->assertTrue(AcademicCycle::where('status', 'closed')->exists());
        $this->assertTrue(AssessmentPeriod::whereNotNull('reopened_at')->whereNotNull('current_report_id')->exists());
        $blocked = AcademicCycle::where('code', 'Ciclo pendiente de resolución')->sole();
        $this->assertSame('active', $blocked->status);
        $this->assertNotEmpty($blocked->closure_issues);
        $future = AcademicCycle::where('code', 'like', 'Ciclo siguiente%')->sole()->offerings()->first();
        $this->assertSame('no_sessions', app(AttendanceSummary::class)->forOffering($admin, $future)['rows']->first()['calculation']['state']);
        $graduate = User::where('graduated', true)->first();
        $this->assertGreaterThan(0, $graduate->enrollments()->count());
        try {
            app(AcademicRegistry::class)->saveEnrollment($admin, $future, ['student_id' => $graduate->id, 'active_from' => $future->cycle->starts_on->toDateString()]);
            $this->fail('An additional graduate enrollment must remain forbidden.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_id', $exception->errors());
        }
        // A second run must preserve changes made during exploration, including passwords.
        $admin->update(['name' => 'Lucía Herrera de Campos', 'password' => 'Changed-local-password-2026!']);
        $fingerprint = $this->fingerprint();
        config(['smartaulaues.exploration_password_hash' => Hash::make('Different-unused-password!')]);
        $this->travelTo($anchor->addMonth());
        $this->seed(ExplorationSeeder::class);
        $this->assertSame($fingerprint, $this->fingerprint());
    }

    private function fingerprint(): string
    {
        return hash('sha256', json_encode([User::orderBy('id')->get()->map(fn ($user) => $user->getAttributes())->all(),
            AcademicCycle::orderBy('id')->get()->toArray(), ReportVersion::orderBy('id')->get()->toArray(),
            AuditLog::count(), Enrollment::count(), ClassSession::count()]));
    }

    public function test_seeding_without_password_fails_before_creating_anything(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            $this->seed(ExplorationSeeder::class);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_existing_accounts_are_never_overwritten(): void
    {
        $user = User::factory()->create(['email' => 'camila.morales@example.test']);
        config(['smartaulaues.exploration_password_hash' => Hash::make('Local-only-password-2026!')]);
        $before = $user->fresh()->getAttributes();
        try {
            $this->seed(ExplorationSeeder::class);
            $this->fail('A collision must stop the preparation.');
        } catch (RuntimeException) {
            $this->assertSame($before, $user->fresh()->getAttributes());
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('academic_cycles', 0);
        }
    }

    public function test_production_and_unrecognized_host_are_rejected_before_any_mutation(): void
    {
        foreach (['production', 'unknown-host'] as $case) {
            app()->instance('env', $case === 'production' ? 'production' : 'testing');
            if ($case === 'unknown-host') {
                config(['database.connections.pgsql.host' => 'unrecognized.example.test']);
            }
            try {
                LocalDatabase::verify(allowTesting: true);
                $this->fail('The target must be rejected.');
            } catch (RuntimeException) {
                $this->assertDatabaseCount('users', 0);
            }
        }
        app()->instance('env', 'testing');
    }
}
