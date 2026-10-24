<?php

/** Native PostgreSQL: prove real lock waits between closure and independent writers. */
use App\Actions\Academic\AcademicRegistry;
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CorrectAttendance;
use App\Actions\Attendance\RegisterAttendance;
use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'siaa_test'
    || DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test'
    || DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test') {
    throw new RuntimeException('Las carreras A03 requieren lectura y escritura exclusivamente en siaa_test.');
}

if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, 'READY '.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL);
    try {
        $actor = User::findOrFail($job['actor']);
        $period = AssessmentPeriod::findOrFail($job['period']);
        match ($job['operation']) {
            'close' => app(ClosureWorkflow::class)->closePeriod($actor, $period),
            'correct' => app(CorrectAttendance::class)->execute($actor, Attendance::findOrFail($job['attendance']), 'present', 'Corrección concurrente A03'),
            'register' => app(RegisterAttendance::class)->record($actor, ClassSession::findOrFail($job['session']), 'manual', $job['data']),
            'enroll' => app(AcademicRegistry::class)->saveEnrollment($actor, $period->offering, ['student_id' => $job['student'], 'active_from' => $period->starts_on->toDateString()]),
            'change-enrollment' => app(AcademicRegistry::class)->saveEnrollment($actor, $period->offering, ['active_from' => $period->ends_on->toDateString(), 'reason' => 'Cambio concurrente de vigencia A03'], Enrollment::findOrFail($job['enrollment'])),
            'reopen' => app(ClosureWorkflow::class)->reopenPeriod($actor, $period, 'Reapertura concurrente A03'),
        };
        $result = ['outcome' => 'ok'];
    } catch (ValidationException $exception) {
        $result = ['outcome' => 'blocked', 'errors' => $exception->errors()];
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    exit;
}

function closureDuringWrite(callable $write, array $job): array
{
    $process = null;
    try {
        DB::transaction(function () use ($write, $job, &$process): void {
            $write();
            $process = new Process([PHP_BINARY, __FILE__, '--env=testing', '--worker'], base_path(), timeout: 40);
            $process->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $process->start();
            $deadline = microtime(true) + 30;
            do {
                if (preg_match('/READY (\d+)/', $process->getOutput(), $match)
                    && DB::selectOne("select count(*) as n from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [(int) $match[1]])->n > 0) {
                    return; // Only commit after PostgreSQL confirms an actual independent waiter.
                }
                if (! $process->isRunning() || microtime(true) >= $deadline) {
                    throw new RuntimeException('No se observó la espera concurrente: '.$process->getErrorOutput());
                }
                usleep(10000);
            } while (true);
        });
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Falló el proceso concurrente: '.$process->getErrorOutput());
        }
        $lines = preg_split('/\R/', trim($process->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if ($process?->isRunning()) {
            $process->stop(1);
        }
    }
}

function checkClosure(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
    fwrite(STDOUT, 'PASS '.$label.PHP_EOL);
}

$fixtures = [];
function closureFixture(): array
{
    global $fixtures;
    $period = AssessmentPeriod::factory()->create(['starts_on' => now('America/El_Salvador')->subDays(2)->toDateString(), 'ends_on' => now('America/El_Salvador')->subDay()->toDateString()]);
    $fixtures[] = $period;
    $period->offering->cycle->update(['starts_on' => $period->starts_on, 'ends_on' => $period->ends_on]);
    $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => $period->starts_on]);
    $admin = User::factory()->coordinator()->create();
    $period->setRelation('testAdmin', $admin);
    $start = now('America/El_Salvador')->subDay()->setTime(10, 0)->utc();
    $session = ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id, 'state' => 'completed', 'starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
    $attendance = Attendance::create(['offering_id' => $period->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent', 'recorded_by' => $admin->id, 'recorded_at' => now()]);

    return [$period, $enrollment, $admin, $session, $attendance];
}

try {
    [$period, $enrollment, $admin, $session, $attendance] = closureFixture();
    $job = ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'correct', 'attendance' => $attendance->id];
    $result = closureDuringWrite(fn () => app(ClosureWorkflow::class)->closePeriod($admin, $period), $job);
    checkClosure($result['outcome'] === 'blocked' && isset($result['errors']['session']) && $attendance->fresh()->status->value === 'absent' && $period->reports()->count() === 1
        && $period->reports()->sole()->snapshot['rows'][0]['calculation']['percentage'] === '0.00', 'cierre → corrección: espera real, corrección bloqueada y snapshot 0 % coherente');
    [$period, $enrollment, $admin, $session, $attendance] = closureFixture();
    $result = closureDuringWrite(fn () => app(CorrectAttendance::class)->execute($admin, $attendance, 'present', 'Corrección confirmada antes de cerrar'), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'close']);
    checkClosure($result['outcome'] === 'ok' && $attendance->fresh()->status->value === 'present' && $period->reports()->count() === 1
        && $period->reports()->sole()->snapshot['rows'][0]['calculation']['percentage'] === '100.00', 'corrección → cierre: snapshot 100 % incluye la escritura confirmada');
    [$period, $enrollment, $admin, $session, $attendance] = closureFixture();
    $request = AbsenceRequest::create(['offering_id' => $period->offering_id, 'enrollment_id' => $enrollment->id, 'session_id' => $session->id, 'status' => 'pending', 'reason' => 'Permiso concurrente ficticio']);
    $result = closureDuringWrite(fn () => app(AbsenceWorkflow::class)->resolve($admin, $request, 'approved', 'Resolución previa al cierre'), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'close']);
    checkClosure($result['outcome'] === 'ok' && $period->fresh()->state === 'closed' && $period->reports()->sole()->snapshot['rows'][0]['calculation']['percentage'] === '100.00', 'permiso → cierre: snapshot posterior a aprobación y justificación atómicas');
    [$period, $enrollment, $admin, $session] = closureFixture();
    $result = closureDuringWrite(fn () => app(ClosureWorkflow::class)->closePeriod($admin, $period), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'close']);
    checkClosure($result['outcome'] === 'ok' && $period->reports()->count() === 1, 'cierre → cierre: una única versión tras dos procesos');
    [$period, $enrollment, $admin, $session] = closureFixture();
    $portal = $session->portals()->create(['opens_at' => $session->starts_at, 'expires_at' => $session->ends_at, 'enabled_methods' => ['manual'], 'token_hash' => hash('sha256', (string) Str::uuid())]);
    $result = closureDuringWrite(fn () => app(ClosureWorkflow::class)->closePeriod($admin, $period), ['actor' => $admin->id, 'period' => $period->id, 'session' => $session->id, 'operation' => 'register', 'data' => ['event_id' => (string) Str::uuid(), 'portal_id' => $portal->id, 'student_id' => $enrollment->student_id, 'reason' => 'Registro concurrente ficticio']]);
    checkClosure($result['outcome'] === 'blocked' && isset($result['errors']['session']) && $session->attendances()->sole()->status->value === 'absent'
        && DB::table('attendance_events')->where('session_id', $session->id)->count() === 0 && $period->reports()->sole()->snapshot['rows'][0]['calculation']['percentage'] === '0.00', 'cierre → registro: bloqueado por cómputo cerrado, sin evento ni cambio del snapshot');
    [$period, $enrollment, $admin] = closureFixture();
    $originalFrom = $enrollment->active_from->toDateString();
    $result = closureDuringWrite(fn () => app(ClosureWorkflow::class)->closePeriod($admin, $period), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'change-enrollment', 'enrollment' => $enrollment->id]);
    checkClosure($result['outcome'] === 'blocked' && isset($result['errors']['active_from']) && $enrollment->fresh()->active_from->toDateString() === $originalFrom
        && $period->reports()->sole()->snapshot['rows'][0]['active_from'] === $originalFrom, 'cierre → inscripción: vigencia válida bloqueada y rango histórico conservado');
    [$period, $enrollment, $admin] = closureFixture();
    $result = closureDuringWrite(fn () => app(AcademicRegistry::class)->saveEnrollment($admin, $period->offering, ['active_from' => $period->ends_on->toDateString(), 'reason' => 'Vigencia confirmada antes del cierre'], $enrollment), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'close']);
    checkClosure($result['outcome'] === 'ok' && $period->reports()->count() === 1 && $enrollment->fresh()->active_from->toDateString() === $period->ends_on->toDateString()
        && $period->reports()->sole()->snapshot['rows'][0]['active_from'] === $period->ends_on->toDateString(), 'inscripción → cierre: snapshot conserva la vigencia confirmada tras espera');
    [$period, $enrollment, $admin] = closureFixture();
    app(ClosureWorkflow::class)->closePeriod($admin, $period);
    $result = closureDuringWrite(fn () => app(ClosureWorkflow::class)->closeCycle($admin, $period->offering->cycle), ['actor' => $admin->id, 'period' => $period->id, 'operation' => 'reopen']);
    checkClosure($result['outcome'] === 'blocked' && $period->fresh()->state === 'closed', 'cierre de ciclo → reapertura de cómputo: exige reabrir primero el ciclo');
} finally {
    foreach ($fixtures as $period) {
        DB::transaction(function () use ($period): void {
            $offering = $period->offering;
            $sessionIds = $offering->sessions()->pluck('id');
            $studentIds = $offering->enrollments()->pluck('student_id');
            $actorIds = [$period->testAdmin?->id, $offering->teacher_id, ...$studentIds];
            DB::table('assessment_periods')->where('id', $period->id)->update(['current_report_id' => null]);
            DB::table('report_versions')->where('period_id', $period->id)->delete();
            DB::table('attendance_events')->whereIn('session_id', $sessionIds)->delete();
            DB::table('absence_requests')->where('offering_id', $offering->id)->delete();
            DB::table('attendances')->where('offering_id', $offering->id)->delete();
            DB::table('attendance_portals')->whereIn('session_id', $sessionIds)->delete();
            DB::table('class_sessions')->where('offering_id', $offering->id)->delete();
            DB::table('enrollments')->where('offering_id', $offering->id)->delete();
            DB::table('assessment_periods')->where('id', $period->id)->delete();
            $offering->delete();
            $offering->subject->delete();
            $offering->cycle->delete();
            DB::table('notifications')->whereIn('notifiable_id', $actorIds)->where('notifiable_type', User::class)->delete();
            DB::table('audit_logs')->whereIn('actor_id', $actorIds)->delete();
            User::whereIn('id', $actorIds)->delete();
        });
    }
}
