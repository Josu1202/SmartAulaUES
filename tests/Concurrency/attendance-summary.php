<?php

/** Native PostgreSQL blocking test: the summary must wait for actual workflow commits. */
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\AttendancePolicy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'siaa_test' || DB::selectOne('select current_database() as name')->name !== 'siaa_test') {
    throw new RuntimeException('Este script requiere exclusivamente siaa_test y entorno testing.');
}
if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, 'READY '.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL);
    if ($job['collective'] ?? false) {
        $batch = app(AttendanceSummary::class)->forOffering(User::findOrFail($job['actor']), Offering::findOrFail($job['offering']), $job['period']);
        $result = $batch['rows']->first()['calculation'];
        $times = $batch['rows']->getCollection()->pluck('calculation.as_of')->unique();
        if ($times->count() !== 1 || $batch['rows']->count() !== 2) {
            throw new RuntimeException('La página colectiva no comparte una lectura coherente.');
        }
    } else {
        $result = app(AttendanceSummary::class)->forEnrollment(User::findOrFail($job['actor']), Enrollment::findOrFail($job['enrollment']), AssessmentPeriod::findOrFail($job['period']));
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    exit;
}

function summaryDuringWrite(callable $write, array $job): array
{
    $process = null;
    try {
        DB::transaction(function () use ($write, $job, &$process): void {
            $write();
            $process = new Process([PHP_BINARY, __FILE__, '--env=testing', '--worker'], base_path(), timeout: 20);
            $process->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $process->start();
            $deadline = microtime(true) + 15;
            do {
                $ready = preg_match('/READY (\d+)/', $process->getOutput(), $matches);
                if ($ready && DB::selectOne("select count(*) as n from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [(int) $matches[1]])->n > 0) {
                    return; // Commit only after PostgreSQL proves the reader is waiting on our lock.
                }
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    throw new RuntimeException('El lector no esperó el bloqueo académico dentro del plazo.');
                }
                usleep(10000);
            } while (true);
        });
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('El lector concurrente falló.');
        }
        $lines = explode("\n", trim($process->getOutput()));

        return json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if ($process?->isRunning()) {
            $process->stop(1);
        }
    }
}

$teacher = $student = $otherStudent = $cycle = $subject = $offering = $period = $session = null;
try {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->create();
    $otherStudent = User::factory()->create();
    $from = now('America/El_Salvador')->subDay()->toDateString();
    $to = now('America/El_Salvador')->addDay()->toDateString();
    $cycle = AcademicCycle::factory()->create(['starts_on' => $from, 'ends_on' => $to, 'status' => 'active']);
    $subject = Subject::factory()->create();
    $offering = Offering::factory()->create(['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
    $period = AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'starts_on' => $from, 'ends_on' => $to, 'policy_version' => AttendancePolicy::VERSION]);
    $enrollment = Enrollment::factory()->create(['offering_id' => $offering->id, 'student_id' => $student->id, 'active_from' => $from]);
    Enrollment::factory()->create(['offering_id' => $offering->id, 'student_id' => $otherStudent->id, 'active_from' => $from]);
    $session = ClassSession::factory()->create(['offering_id' => $offering->id, 'period_id' => $period->id, 'state' => 'in_progress', 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinute()]);
    $request = AbsenceRequest::create(['offering_id' => $offering->id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'pending', 'reason' => 'Fixture ficticio de concurrencia A01']);
    $job = ['actor' => $teacher->id, 'enrollment' => $enrollment->id, 'period' => $period->id, 'offering' => $offering->id, 'collective' => true];
    $result = summaryDuringWrite(fn () => app(SessionWorkflow::class)->transition($teacher, $session, 'finish'), $job);
    if ($result['state'] !== 'provisional' || $result['counts']['absent'] !== 1 || $result['counts']['missing'] !== 0 || $result['counts']['pending_requests'] !== 1) {
        throw new RuntimeException('La lectura mezcló estados durante la finalización.');
    }
    fwrite(STDOUT, "PASS página colectiva bloqueada por finalización: dos filas coherentes tras commit\n");
    $result = summaryDuringWrite(fn () => app(AbsenceWorkflow::class)->resolve($teacher, $request, 'approved', 'Resolución concurrente de prueba'), $job);
    if ($result['percentage'] !== '100.00' || $result['counts']['justified'] !== 1 || $result['counts']['pending_requests'] !== 0 || $result['incidents'] !== []) {
        throw new RuntimeException('La lectura mezcló estados durante la resolución.');
    }
    fwrite(STDOUT, "PASS página colectiva bloqueada por resolución: permiso y justificación coherentes tras commit\n");
} finally {
    DB::transaction(function () use ($teacher, $student, $otherStudent, $cycle, $subject, $offering, $period, $session): void {
        if ($session) {
            AbsenceRequest::where('session_id', $session->id)->delete();
            Attendance::where('session_id', $session->id)->delete();
            $session->delete();
        }
        if ($offering) {
            Enrollment::where('offering_id', $offering->id)->delete();
            $period?->delete();
            $offering->delete();
        }
        $subject?->delete();
        $cycle?->delete();
        $ids = array_filter([$teacher?->id, $student?->id, $otherStudent?->id]);
        DB::table('notifications')->whereIn('notifiable_id', $ids)->where('notifiable_type', User::class)->delete();
        DB::table('audit_logs')->whereIn('actor_id', $ids)->delete();
        User::whereIn('id', $ids)->delete();
    });
}
