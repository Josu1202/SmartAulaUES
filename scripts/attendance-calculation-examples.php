<?php

/** Executable consumer examples with disposable fixtures, always rolled back in siaa_test. */
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Queries\AttendanceSummary;
use Illuminate\Support\Facades\DB;

require __DIR__.'/check-test-database.php';
DB::beginTransaction();
try {
    $from = now('America/El_Salvador')->subDay()->toDateString();
    $to = now('America/El_Salvador')->addDay()->toDateString();
    $cycle = AcademicCycle::factory()->create(['starts_on' => $from, 'ends_on' => $to]);
    $offering = Offering::factory()->create(['cycle_id' => $cycle->id]);
    $period = AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'starts_on' => $from, 'ends_on' => $to]);
    $teacher = $period->offering->teacher;
    $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => $from]);
    for ($i = 0; $i < 10; $i++) {
        $session = ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id, 'state' => 'in_progress', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        if ($i < 8) {
            Attendance::create(['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'offering_id' => $period->offering_id, 'status' => 'present', 'recorded_by' => $teacher->id, 'recorded_at' => now()]);
        }
        app(SessionWorkflow::class)->transition($teacher, $session, 'finish');
    }
    $permission = app(AbsenceWorkflow::class)->submit($enrollment->student, $session, 'Permiso ficticio para ejemplo ejecutable de A01', null);
    app(AbsenceWorkflow::class)->resolve($teacher, $permission, 'approved', 'Aprobado para el ejemplo de datos ficticios');
    $summary = app(AttendanceSummary::class);
    $individual = $summary->forEnrollment($teacher, $enrollment, $period);
    $batch = $summary->forOffering($teacher, $period->offering, $period->id, page: 1, perPage: 20);
    echo json_encode(['individual' => $individual, 'collective' => ['period_id' => $batch['period']->id, 'total_visible_enrollments' => $batch['rows']->total(), 'rows' => $batch['rows']->items()]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::rollBack();
}
