<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Queries\AttendanceSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class A05JourneyTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    public function test_import_password_attendance_permission_snapshot_history_and_reopening_form_one_journey(): void
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 24, 16, 0));
        $admin = User::factory()->coordinator()->create();
        $this->asUser($admin)->post('/admin/imports/preview', [
            'kind' => 'students', 'file' => UploadedFile::fake()->createWithContent('journey.csv', "name,email,student_code,entry_year,study_year\nA05 Presente,journey-present@example.test,J001,2026,1\nA05 Permiso,journey-permit@example.test,J002,2022,5\n"),
            'initial_password' => 'A05-journey-initial!', 'initial_password_confirmation' => 'A05-journey-initial!',
        ])->assertSessionHasNoErrors();
        $this->post('/admin/imports/confirm', ['nonce' => session('academic_import_id')])->assertSessionHasNoErrors();
        $present = User::where('student_code', 'J001')->sole();
        $permit = User::where('student_code', 'J002')->sole();
        foreach ([$present, $permit] as $user) {
            $this->asUser($user)->get('/sessions')->assertRedirect('/profile');
            $this->put('/profile/password', ['current_password' => 'A05-journey-initial!', 'password' => 'A05-journey-personal-'.$user->id, 'password_confirmation' => 'A05-journey-personal-'.$user->id])->assertRedirect('/login');
            $this->assertFalse($user->fresh()->must_change_password);
        }
        $teacher = User::factory()->teacher()->create();
        $cycle = AcademicCycle::factory()->create(['ends_on' => '2026-10-24']);
        $subject = Subject::factory()->create();
        $registry = app(AcademicRegistry::class);
        $group = $registry->saveOffering($admin, ['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'group_code' => 'A05', 'capacity' => 10]);
        $period = $registry->savePeriod($admin, $group, ['number' => 1, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-24']);
        foreach ([$present, $permit] as $user) {
            $registry->saveEnrollment($admin, $group, ['student_id' => $user->id, 'active_from' => '2026-10-01']);
        }
        $session = app(SessionWorkflow::class)->save($teacher, $group, ['period_id' => $period->id, 'starts_at' => '2026-10-24T10:00', 'ends_at' => '2026-10-24T11:00', 'modality' => 'online']);
        $this->asUser($teacher)->post('/sessions/'.$session->id.'/state/start')->assertSessionHasNoErrors();
        $portal = $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['link'], 'minutes' => 60])->assertCreated()->json();
        parse_str(parse_url($portal['url'], PHP_URL_FRAGMENT), $secret);
        $this->asUser($present->fresh())->postJson('/check-in/'.$portal['portal_id'], ['event_id' => (string) Str::uuid(), 'portal_token' => $secret['t'], 'method' => 'link'])->assertCreated();
        $this->travelTo($session->ends_at);
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('completed', $session->fresh()->state->value);
        $this->asUser($permit->fresh())->post('/sessions/'.$session->id.'/request', ['reason' => 'Permiso posterior a finalización A05'])->assertSessionHasNoErrors();
        $permission = $session->absenceRequests()->sole();
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 25, 6, 0));
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('open', $period->fresh()->state);
        $this->assertDatabaseCount('report_versions', 0);
        $this->asUser($teacher)->post('/requests/'.$permission->id.'/resolve', ['decision' => 'approved', 'note' => 'Evidencia y motivo revisados A05'])->assertSessionHasNoErrors();
        $this->asUser($admin)->post('/admin/periods/'.$period->id.'/close')->assertSessionHasNoErrors();
        $this->assertSame('closed', $period->fresh()->state);
        $hash = $period->reports()->sole()->snapshot_hash;
        $this->post('/admin/cycles/'.$cycle->id.'/close')->assertSessionHasNoErrors();
        $this->assertSame('closed', $cycle->fresh()->status);
        $this->asUser($present->fresh())->get('/offerings?view=history')->assertOk()->assertSee($subject->name);
        $this->get('/offerings/'.$group->id)->assertOk()->assertSee('100.00 %')->assertDontSee('A05 Permiso');
        $this->asUser($admin)->post('/admin/cycles/'.$cycle->id.'/reopen', ['reason' => 'Revisión autorizada integral A05'])->assertSessionHasNoErrors();
        $this->post('/admin/periods/'.$period->id.'/reopen', ['reason' => 'Revisión autorizada integral A05'])->assertSessionHasNoErrors();
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('open', $period->fresh()->state);
        $this->assertNotNull($period->fresh()->reopened_at);
        $this->assertSame($hash, $period->reports()->sole()->snapshot_hash);
        $result = app(AttendanceSummary::class)->forEnrollment($present->fresh(), Enrollment::where('student_id', $present->id)->sole(), $period->fresh(), reportVersion: 1);
        $this->assertSame('snapshot', $result['source']);
        $this->assertSame('100.00', $result['percentage']);
    }

    public function test_password_rules_reject_bcrypt_truncation_for_ascii_and_utf8(): void
    {
        $admin = User::factory()->coordinator()->create();
        foreach ([str_repeat('a', 73), str_repeat('ñ', 37), 'Valid-prefix'."\0".'secret'] as $password) {
            $this->asUser($admin)->post('/admin/imports/preview', ['kind' => 'teachers',
                'file' => UploadedFile::fake()->createWithContent('password.csv', "name,email\nA05,a05-password@example.test\n"),
                'initial_password' => $password, 'initial_password_confirmation' => $password,
            ])->assertSessionHasErrors('initial_password');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_reading_group_does_not_acquire_exclusive_locks_or_recalculate_collective_deactivation(): void
    {
        $period = AssessmentPeriod::factory()->create();
        Enrollment::factory()->count(50)->create(['offering_id' => $period->offering_id]);
        $admin = User::factory()->coordinator()->create();
        DB::enableQueryLog();
        $this->asUser($admin)->get('/offerings/'.$period->offering_id)->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertFalse($queries->contains(fn ($sql) => str_contains(strtolower($sql), 'for update')));
        // One visible page of academic results, not every enrollment for the diagnostic.
        $this->assertLessThanOrEqual(1, $queries->filter(fn ($sql) => str_contains($sql, '"attendances"') && str_contains($sql, '"enrollment_id" in'))->count());
    }

    public function test_sessions_and_permissions_share_catalog_filters_and_keep_suspended_pending_work_findable(): void
    {
        $admin = User::factory()->coordinator()->create();
        $rows = [];
        foreach (['current', 'inactive', 'history'] as $state) {
            $period = AssessmentPeriod::factory()->create();
            $period->offering->forceFill(['active' => $state !== 'inactive'])->save();
            if ($state === 'history') {
                $period->offering->cycle->update(['status' => 'closed']);
            }
            $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
            $session = ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id]);
            $permission = AbsenceRequest::create(['offering_id' => $period->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'pending', 'reason' => 'Permiso sintético de filtro A05']);
            $rows[$state] = [$session, $permission];
        }
        $this->asUser($admin)->get('/sessions')->assertOk()->assertViewHas('sessions', fn ($page) => $page->modelKeys() === [$rows['current'][0]->id]);
        $this->get('/sessions?availability=inactive')->assertOk()->assertViewHas('sessions', fn ($page) => $page->modelKeys() === [$rows['inactive'][0]->id]);
        $this->get('/sessions?view=history')->assertOk()->assertViewHas('sessions', fn ($page) => $page->modelKeys() === [$rows['history'][0]->id]);
        $this->get('/requests?status=pending')->assertOk()->assertViewHas('requests', fn ($page) => $page->count() === 2 && $page->contains('id', $rows['inactive'][1]->id));
        $this->get('/requests?view=history')->assertOk()->assertViewHas('requests', fn ($page) => $page->modelKeys() === [$rows['history'][1]->id]);
    }

    public function test_reschedule_notifies_only_active_students_enrolled_on_the_session_date(): void
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 24, 16, 30));
        $period = AssessmentPeriod::factory()->create();
        $session = ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $eligible = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-10-25']);
        Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_to' => '2026-10-23']);
        Enrollment::factory()->create(['offering_id' => $period->offering_id, 'student_id' => User::factory()->inactive()->create()->id]);
        app(SessionWorkflow::class)->save($period->offering->teacher, $period->offering, ['period_id' => $period->id,
            'starts_at' => $session->starts_at->timezone('America/El_Salvador')->format('Y-m-d\TH:i'), 'ends_at' => $session->ends_at->timezone('America/El_Salvador')->format('Y-m-d\TH:i'),
            'modality' => 'online', 'reason' => 'Aviso de reprogramación verificado A05'], $session);
        $this->assertSame([$eligible->student_id], DB::table('notifications')->pluck('notifiable_id')->all());
    }
}
