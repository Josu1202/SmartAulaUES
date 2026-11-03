<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TeacherAttendanceMatrixTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function makeClass(AssessmentPeriod $period, string $date, string $state = 'completed'): ClassSession
    {
        $start = CarbonImmutable::parse($date.' 15:00:00', 'UTC');

        return ClassSession::factory()->create([
            'period_id' => $period->id,
            'offering_id' => $period->offering_id,
            'state' => $state,
            'change_reason' => $state === 'cancelled' ? 'Cancelación ficticia registrada para prueba de matriz' : null,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
    }

    private function record(ClassSession $class, Enrollment $student, string $status): void
    {
        Attendance::create([
            'offering_id' => $class->offering_id,
            'session_id' => $class->id,
            'enrollment_id' => $student->id,
            'status' => $status,
            'recorded_by' => $class->offering->teacher_id,
            'recorded_at' => now(),
        ]);
        if ($status === 'justified') {
            AbsenceRequest::create([
                'offering_id' => $class->offering_id,
                'session_id' => $class->id,
                'enrollment_id' => $student->id,
                'status' => 'approved',
                'reason' => 'Permiso ficticio válido para probar la matriz de asistencia',
            ]);
        }
    }

    public function test_matrix_uses_authorized_facts_and_separates_vigency_cancelled_and_future_classes(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $alicia = Enrollment::factory()->create([
            'offering_id' => $period->offering_id,
            'active_from' => '2026-06-03',
            'active_to' => '2026-06-08',
        ]);
        $bruno = Enrollment::factory()->create(['offering_id' => $period->offering_id]);

        $first = $this->makeClass($period, '2026-06-01');
        $this->record($first, $bruno, 'present');
        $second = $this->makeClass($period, '2026-06-03');
        $this->record($second, $alicia, 'absent');
        $this->record($second, $bruno, 'justified');
        $third = $this->makeClass($period, '2026-06-08');
        $this->record($third, $alicia, 'present');
        $this->record($third, $bruno, 'absent');
        $this->makeClass($period, '2026-06-10', 'cancelled');
        $this->makeClass($period, '2026-06-30', 'scheduled');

        $response = $this->asUser($period->offering->teacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $response->assertOk()->assertSee('Matriz de asistencia por estudiante y sesión')
            ->assertSee('Fuera de vigencia')->assertSee('Justificada')->assertSee('Cancelada');

        $matrix = $response->viewData('absenceRanking')['matrix'];
        $this->assertSame(2, $matrix['total']);
        $this->assertSame([$first->id, $second->id, $third->id], array_slice(array_column($matrix['sessions'], 'id'), 0, 3));
        $aliceRow = collect($matrix['rows'])->firstWhere('enrollment_id', $alicia->id);
        $this->assertFalse($aliceRow['facts'][$first->id]['in_range']);
        $this->assertTrue($aliceRow['facts'][$third->id]['in_range']);
        $this->assertSame('absent', $aliceRow['facts'][$second->id]['status']);
        $this->assertSame('present', $aliceRow['facts'][$third->id]['status']);
        $brunoRow = collect($matrix['rows'])->firstWhere('enrollment_id', $bruno->id);
        $this->assertSame('justified', $brunoRow['facts'][$second->id]['status']);
        $this->assertSame('approved', $brunoRow['facts'][$second->id]['request_status']);
        $this->assertCount(5, $matrix['sessions']);
    }

    public function test_matrix_pages_students_without_losing_session_columns(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        Enrollment::factory()->count(16)->create(['offering_id' => $period->offering_id]);
        $this->makeClass($period, '2026-06-03');
        $this->makeClass($period, '2026-06-30', 'scheduled');
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;

        $first = $this->asUser($period->offering->teacher)->get($url);
        $first->assertOk()->assertSee('Matriz de asistencia por estudiante y sesión');
        $this->assertSame(16, $first->viewData('absenceRanking')['matrix']['total']);
        $this->assertCount(15, $first->viewData('absenceRanking')['matrix']['rows']);
        $this->assertCount(2, $first->viewData('absenceRanking')['matrix']['sessions']);

        $second = $this->asUser($period->offering->teacher)->get($url.'&matrix_page=2');
        $second->assertOk();
        $this->assertCount(1, $second->viewData('absenceRanking')['matrix']['rows']);
        $this->assertCount(2, $second->viewData('absenceRanking')['matrix']['sessions']);
        $this->assertSame(2, $second->viewData('absenceRanking')['matrix']['pages']);
        $this->asUser($period->offering->teacher)->get($url.'&matrix_page=3')->assertNotFound();
    }

    public function test_student_and_foreign_teacher_cannot_read_the_collective_matrix(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id])->student;
        $foreignTeacher = User::factory()->teacher()->create();
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.'&matrix_page=1';

        $this->asUser($foreignTeacher)->get($url)->assertNotFound();
        $this->asUser($student)->get($url)
            ->assertOk()->assertDontSee('Matriz de asistencia por estudiante y sesión');
    }
}
