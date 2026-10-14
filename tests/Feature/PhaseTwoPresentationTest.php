<?php

namespace Tests\Feature;

use App\Actions\Academic\CycleFormatManager;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use App\Queries\DashboardStatistics;
use Tests\TestCase;

class PhaseTwoPresentationTest extends TestCase
{
    public function test_new_pages_render_and_empty_dashboard_does_not_invent_statistics(): void
    {
        $actor = User::factory()->coordinator()->create();
        $this->actingAs($actor)->get('/dashboard')->assertOk()->assertSee('Aún no hay datos');
        foreach (['/admin/students', '/admin/teachers', '/admin/students/batch', '/admin/formats', '/admin/formats/create', '/admin/nfc-credentials', '/sessions', '/requests', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $session = ClassSession::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $format = app(CycleFormatManager::class)->save($actor, ['cycle_id' => $session->offering->cycle_id, 'name' => 'Formato de ejemplo', 'active' => true, 'periods' => [['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']]]);
        $this->get('/admin/formats/'.$format->id.'/edit')->assertOk();
        $this->get('/admin/offerings/'.$session->offering_id.'/students')->assertOk();
        $this->get('/offerings/'.$session->offering_id.'/sessions/create')->assertOk();
        $this->get('/offerings/'.$session->offering_id.'/sessions/'.$session->id.'/edit')->assertOk();
        $this->get('/sessions/'.$session->id)->assertOk();
        $student = $enrollment->student;
        $this->withSession(['password_hash_web' => $student->password])->actingAs($student)->get('/sessions/'.$session->id.'/request')->assertOk();
        $request = AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'reason' => 'Solicitud de prueba']);
        $this->get('/requests/'.$request->id)->assertOk();
    }

    public function test_student_statistics_only_count_own_actual_results(): void
    {
        $session = ClassSession::factory()->create(['state' => 'completed']);
        $a = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $b = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        foreach ([[$a, 'present'], [$b, 'absent']] as [$enrollment,$status]) {
            Attendance::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => $status, 'method' => $status === 'present' ? 'qr' : null, 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now()]);
        }
        AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $b->id, 'reason' => 'Solicitud pendiente real']);
        $statistics = app(DashboardStatistics::class)->forUser($a->student);
        $this->assertSame(['Presentes' => 1, 'Ausentes' => 0, 'Justificadas' => 0], $statistics['results']);
        $this->assertSame(1, $statistics['methods']['QR']);
        $this->assertSame([], $statistics['years']);
        $this->assertSame(0, $statistics['pending_requests']);
        $this->assertSame(1, app(DashboardStatistics::class)->forUser($b->student)['pending_requests']);
        $this->assertSame(1, app(DashboardStatistics::class)->forUser($session->offering->teacher)['pending_requests']);
        $this->assertSame(0, app(DashboardStatistics::class)->forUser(User::factory()->teacher()->create())['pending_requests']);
        $this->actingAs($a->student)->get('/dashboard')->assertOk()->assertSee('Resultados registrados');
    }
}
