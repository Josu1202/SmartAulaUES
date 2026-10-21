<?php

namespace Tests\Feature;

use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\AttendanceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceResultsTableTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);

        return [$period, $enrollment, $period->offering->teacher];
    }

    private function record(AssessmentPeriod $period, Enrollment $enrollment, string $status = 'present', string $date = '2026-06-01 14:00:00'): ClassSession
    {
        $session = ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id, 'state' => 'completed', 'starts_at' => $date, 'ends_at' => CarbonImmutable::parse($date, 'UTC')->addHour()]);
        Attendance::create(['session_id' => $session->id, 'offering_id' => $period->offering_id, 'enrollment_id' => $enrollment->id, 'status' => $status, 'recorded_by' => $period->offering->teacher_id, 'recorded_at' => now()]);

        return $session;
    }

    public function test_real_permission_flow_changes_reloaded_table_from_eighty_to_ninety_without_duplicates(): void
    {
        [$period, $enrollment, $teacher] = $this->scenario();
        for ($i = 0; $i < 8; $i++) {
            $this->record($period, $enrollment);
        }
        $this->record($period, $enrollment, 'absent');
        $session = ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id, 'state' => 'in_progress', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        app(SessionWorkflow::class)->transition($teacher, $session, 'finish');
        $permission = app(AbsenceWorkflow::class)->submit($enrollment->student, $session, 'Permiso posterior documentado para prueba A01', null);
        $attendanceId = $session->attendances()->sole()->id;
        $this->actingAs($teacher)->get('/offerings/'.$period->offering_id.'?period_id='.$period->id)->assertOk()->assertSee('80.00 %')->assertSee('El resultado puede cambiar.');
        $this->post('/requests/'.$permission->id.'/resolve', ['decision' => 'approved', 'note' => 'Permiso verificado por el docente'])->assertSessionHasNoErrors();
        $this->get('/offerings/'.$period->offering_id.'?period_id='.$period->id)->assertOk()->assertSee('90.00 %')->assertDontSee('80.00 %');
        $this->assertDatabaseCount('attendances', 10);
        $this->assertDatabaseHas('attendances', ['id' => $attendanceId, 'status' => 'justified']);
        $this->assertDatabaseCount('absence_requests', 1);
        $this->post('/requests/'.$permission->id.'/resolve', ['decision' => 'approved', 'note' => 'Segundo intento de la misma resolución'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('attendances', 10);
        $result = app(AttendanceSummary::class)->forOffering($teacher, $period->offering, $period->id)['rows']->first()['calculation'];
        $this->assertSame(['numerator' => 9, 'denominator' => 10], $result['fraction']);
        $this->assertSame(8, $result['counts']['present']);
        $this->assertSame(1, $result['counts']['justified']);
        $this->assertSame(1, $result['counts']['absent']);
    }

    public function test_collective_and_table_preserve_late_enrollment_inclusive_withdrawal_and_inactive_history(): void
    {
        [$period, $withdrawn, $teacher] = $this->scenario();
        $withdrawn->student->update(['name' => 'HISTORIAL_INACTIVO', 'active' => false]);
        $withdrawn->update(['active_from' => '2026-06-01', 'active_to' => '2026-06-01']);
        $late = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-06-02']);
        $outside = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-07-01']);
        $period->update(['ends_on' => '2026-06-30']);
        $a = $this->record($period, $withdrawn, date: '2026-06-02 05:30:00'); // June 1 in El Salvador.
        $b = $this->record($period, $late, date: '2026-06-02 06:30:00');
        $rows = app(AttendanceSummary::class)->forOffering($teacher, $period->offering)['rows']->getCollection()->keyBy('enrollment_id');
        $this->assertSame('100.00', $rows[$withdrawn->id]['calculation']['percentage']);
        $this->assertSame(1, $rows[$withdrawn->id]['calculation']['counts']['eligible']);
        $this->assertSame('100.00', $rows[$late->id]['calculation']['percentage']);
        $this->assertSame(1, $rows[$late->id]['calculation']['counts']['eligible']);
        $this->assertSame('no_sessions', $rows[$outside->id]['calculation']['state']);
        $this->assertNull($rows[$outside->id]['calculation']['percentage']);
        foreach ([$teacher, User::factory()->coordinator()->create()] as $actor) {
            $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor)->get('/offerings/'.$period->offering_id)->assertOk()->assertSee('HISTORIAL_INACTIVO')->assertSee('historial conservado')->assertSee('Sin clases evaluables')->assertSee('100.00 %');
        }
        $this->withSession(['password_hash_web' => $withdrawn->student->password])->actingAs($withdrawn->student)->get('/offerings/'.$period->offering_id)->assertRedirect('/login');
    }

    public function test_pagination_limits_students_but_never_sessions_and_preserves_period_selection(): void
    {
        [$period, $first, $teacher] = $this->scenario();
        $secondPeriod = AssessmentPeriod::factory()->create(['offering_id' => $period->offering_id, 'number' => 2]);
        $enrollments = collect([$first]);
        for ($i = 0; $i < 20; $i++) {
            $enrollments->push(Enrollment::factory()->create(['offering_id' => $period->offering_id]));
        }
        for ($i = 0; $i < 25; $i++) {
            $session = $this->record($secondPeriod, $first);
            foreach ($enrollments->slice(1) as $enrollment) {
                Attendance::create(['offering_id' => $period->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => $i < 20 ? 'present' : 'absent', 'recorded_by' => $teacher->id, 'recorded_at' => now()]);
            }
        }
        $summary = app(AttendanceSummary::class);
        $pageOne = $summary->forOffering($teacher, $period->offering, $secondPeriod->id);
        $pageTwo = $summary->forOffering($teacher, $period->offering, $secondPeriod->id, page: 2);
        $this->assertCount(20, $pageOne['rows']);
        $this->assertCount(1, $pageTwo['rows']);
        $this->assertSame(21, $pageTwo['rows']->total());
        $this->assertSame(25, $pageTwo['rows']->first()['calculation']['counts']['eligible']);
        $this->assertSame('80.00', $pageTwo['rows']->first()['calculation']['percentage']);
        $this->assertStringContainsString('period_id='.$secondPeriod->id, $pageOne['rows']->nextPageUrl());
        $this->assertStringContainsString('results_page=2', $pageOne['rows']->nextPageUrl());
        $this->actingAs($teacher)->get('/offerings/'.$period->offering_id.'?period_id='.$secondPeriod->id.'&results_page=2')->assertOk()->assertSee('80.00 %')->assertSee('value="'.$secondPeriod->id.'" selected', false);
    }

    public function test_collective_read_has_constant_query_count_for_a_page_and_performs_no_writes(): void
    {
        [$period, $enrollment, $teacher] = $this->scenario();
        Enrollment::factory()->count(8)->create(['offering_id' => $period->offering_id]);
        $this->record($period, $enrollment);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(AttendanceSummary::class)->forOffering($teacher, $period->offering, perPage: 1);
        $one = DB::getQueryLog();
        DB::flushQueryLog();
        $batch = app(AttendanceSummary::class)->forOffering($teacher, $period->offering, perPage: 20);
        $many = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(count($one), count($many));
        $this->assertLessThanOrEqual(14, count($many));
        $this->assertCount(1, $batch['rows']->getCollection()->pluck('calculation.as_of')->unique());
        foreach ($many as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|create|alter|drop)\b/i', $query['query']);
        }
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_profiles_selector_and_private_incidents_are_scoped_to_the_authorized_resource(): void
    {
        [$period, $mine, $teacher] = $this->scenario();
        $mine->student->update(['name' => 'ESTUDIANTE_TITULAR']);
        $other = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $other->student->update(['name' => 'ESTUDIANTE_PRIVADO', 'student_code' => 'PRIVADO001']);
        $session = $this->record($period, $mine);
        AbsenceRequest::create(['session_id' => $session->id, 'offering_id' => $period->offering_id, 'enrollment_id' => $other->id, 'status' => 'pending', 'reason' => 'MOTIVO_PRIVADO']);
        $url = '/offerings/'.$period->offering_id;
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($mine->student)->get($url)->assertOk()->assertSee('100.00 %')->assertDontSee('ESTUDIANTE_PRIVADO')->assertDontSee('PRIVADO001')->assertDontSee('MOTIVO_PRIVADO')->assertDontSee('Falta el resultado')->assertDontSee('Permisos pendientes:');
        $this->withSession(['password_hash_web' => $teacher->password])->actingAs($teacher)->get($url)->assertOk()->assertSee('ESTUDIANTE_PRIVADO')->assertSee('Datos incompletos')->assertSee('Falta el resultado');
        $foreign = User::factory()->teacher()->create();
        $this->withSession(['password_hash_web' => $foreign->password])->actingAs($foreign)->get($url)->assertForbidden();
        $outsider = User::factory()->create();
        $this->withSession(['password_hash_web' => $outsider->password])->actingAs($outsider)->get($url)->assertForbidden();
        $this->withSession(['password_hash_web' => $teacher->password])->actingAs($teacher)->get($url.'?period_id='.AssessmentPeriod::factory()->create()->id)->assertNotFound();
        $this->get($url.'?period_id[]=1')->assertSessionHasErrors('period_id');
        $this->get($url.'?results_page=0')->assertSessionHasErrors('results_page');
    }

    public function test_initial_period_selection_is_lowest_number_and_empty_offering_is_explained(): void
    {
        $offering = Offering::factory()->create();
        $this->actingAs($offering->teacher)->get('/offerings/'.$offering->id)->assertOk()->assertSee('no tiene cómputos definidos');
        AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'number' => 3]);
        $first = AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'number' => 1]);
        $this->assertSame($first->id, app(AttendanceSummary::class)->forOffering($offering->teacher, $offering)['period']->id);
    }
}
