<?php

/** A04: independent PostgreSQL processes; confirm actual lock waits, then commit the winner. */
use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\OfferingLifecycle;
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    throw new RuntimeException('A04 requiere lectura y escritura efectivas exclusivamente en siaa_test.');
}

if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, 'READY '.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL);
    try {
        $actor = User::findOrFail($job['actor']);
        $group = Offering::findOrFail($job['group']);
        match ($job['operation']) {
            'disable' => app(OfferingLifecycle::class)->setActive($actor, $group, false, 'Suspensión concurrente A04'),
            'enable' => app(OfferingLifecycle::class)->setActive($actor, $group, true, 'Reactivación concurrente A04'),
            'delete' => app(OfferingLifecycle::class)->delete($actor, $group, $group->group_code, 'Grupo vacío creado por error A04'),
            'enroll' => app(AcademicRegistry::class)->saveEnrollment($actor, $group, ['student_id' => $job['student'], 'active_from' => $group->cycle->starts_on->toDateString()]),
            'schedule' => app(SessionWorkflow::class)->save($actor, $group, $job['data']),
            'start' => app(SessionWorkflow::class)->transition($actor, ClassSession::findOrFail($job['session']), 'start'),
            'portal' => app(PortalManager::class)->open($actor, ClassSession::findOrFail($job['session']), ['manual'], 5),
            'register' => app(RegisterAttendance::class)->record($actor, ClassSession::findOrFail($job['session']), 'manual', $job['data']),
            'permission' => app(AbsenceWorkflow::class)->submit($actor, ClassSession::findOrFail($job['session']), 'Permiso concurrente ficticio A04', null),
            'close-cycle' => app(ClosureWorkflow::class)->closeCycle($actor, $group->cycle),
            'reopen-cycle' => app(ClosureWorkflow::class)->reopenCycle($actor, $group->cycle, 'Revisión concurrente del ciclo A04'),
        };
        $result = ['outcome' => 'ok'];
    } catch (ValidationException $exception) {
        $result = ['outcome' => 'blocked', 'errors' => $exception->errors()];
    } catch (ModelNotFoundException) {
        $result = ['outcome' => 'missing'];
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    exit;
}

function a04Race(callable $first, array $job): array
{
    $process = null;
    try {
        DB::transaction(function () use ($first, $job, &$process): void {
            $first();
            $process = new Process([PHP_BINARY, __FILE__, '--env=testing', '--worker'], base_path(), timeout: 45);
            $process->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $process->start();
            $deadline = microtime(true) + 35;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                if (preg_match('/READY (\d+)/', $process->getOutput(), $match)
                    && DB::selectOne("select count(*) as n from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [(int) $match[1]])->n > 0) {
                    return;
                }
                if (! $process->isRunning() || microtime(true) >= $deadline) {
                    throw new RuntimeException('No hubo espera PostgreSQL: '.$process->getOutput().' '.$process->getErrorOutput());
                }
                usleep(10000);
            } while (true);
        });
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $lines = preg_split('/\R/', trim($process->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if ($process?->isRunning()) {
            $process->stop(1);
        }
    }
}

function a04Check(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
    fwrite(STDOUT, 'PASS espera PostgreSQL independiente: '.$label.PHP_EOL);
}

$groups = [];
$createdUsers = [];
function a04Fixture(bool $period = true): array
{
    global $groups, $createdUsers;
    $group = Offering::factory()->create();
    $groups[] = $group;
    $admin = User::factory()->coordinator()->create();
    $student = User::factory()->create();
    $createdUsers = [...$createdUsers, $admin->id, $student->id, $group->teacher_id];
    $assessment = $period ? AssessmentPeriod::factory()->create(['offering_id' => $group->id]) : null;
    $session = $period ? ClassSession::factory()->create(['offering_id' => $group->id, 'period_id' => $assessment->id,
        'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50), 'modality' => 'online']) : null;

    return [$group, $admin, $student, $assessment, $session];
}

try {
    foreach (['start', 'portal', 'register', 'permission', 'schedule', 'enroll', 'disable'] as $operation) {
        [$group, $admin, $student, $period, $session] = a04Fixture();
        if ($operation === 'permission') {
            Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id]);
        }
        $data = $operation === 'schedule'
            ? ['period_id' => $period->id, 'starts_at' => now('America/El_Salvador')->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now('America/El_Salvador')->addDay()->addHour()->format('Y-m-d\TH:i'), 'modality' => 'online']
            : ['event_id' => (string) Str::uuid(), 'portal_id' => 1, 'student_id' => $student->id, 'reason' => 'Registro concurrente A04'];
        $job = ['operation' => $operation, 'actor' => $operation === 'permission' ? $student->id : $admin->id,
            'group' => $group->id, 'session' => $session->id, 'student' => $student->id, 'data' => $data];
        $result = a04Race(fn () => app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspensión anterior al escritor concurrente'), $job);
        a04Check($result['outcome'] === ($operation === 'disable' ? 'ok' : 'blocked') && ! $group->fresh()->active
            && DB::table('audit_logs')->where('entity_type', 'Offering')->where('entity_id', (string) $group->id)->where('action', 'offering.deactivated')->count() === 1,
            'desactivar → '.$operation.': sin actividad nueva ni auditoría duplicada');
    }

    [$group, $admin, , , $session] = a04Fixture();
    $result = a04Race(fn () => app(SessionWorkflow::class)->transition($admin, $session, 'start'), ['operation' => 'disable', 'actor' => $admin->id, 'group' => $group->id]);
    a04Check($result['outcome'] === 'blocked' && $group->fresh()->active && $session->fresh()->state->value === 'in_progress', 'iniciar → desactivar: sesión conserva su inicio; suspensión bloqueada');

    [$group, $admin, $student, , $session] = a04Fixture();
    Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id]);
    $result = a04Race(fn () => app(AbsenceWorkflow::class)->submit($student, $session, 'Permiso antes de suspender A04', null), ['operation' => 'disable', 'actor' => $admin->id, 'group' => $group->id]);
    a04Check($result['outcome'] === 'blocked' && $group->fresh()->active, 'permiso → desactivar: pendiente conservado y suspensión bloqueada');

    [$group, $admin, $student] = a04Fixture(false);
    $result = a04Race(fn () => app(OfferingLifecycle::class)->delete($admin, $group, $group->group_code, 'Grupo vacío eliminado por error A04'), ['operation' => 'enroll', 'actor' => $admin->id, 'group' => $group->id, 'student' => $student->id]);
    a04Check($result['outcome'] === 'missing' && ! Offering::whereKey($group->id)->exists() && ! Enrollment::where('offering_id', $group->id)->exists(), 'eliminar → inscribir: sin grupo ni dependencia huérfana');

    [$group, $admin, $student] = a04Fixture(false);
    $result = a04Race(fn () => app(AcademicRegistry::class)->saveEnrollment($admin, $group, ['student_id' => $student->id, 'active_from' => $group->cycle->starts_on->toDateString()]), ['operation' => 'delete', 'actor' => $admin->id, 'group' => $group->id]);
    a04Check($result['outcome'] === 'blocked' && Offering::whereKey($group->id)->exists() && $group->enrollments()->count() === 1, 'inscribir → eliminar: grupo e inscripción conservados');

    foreach (['enable-first', 'close-first', 'reopen-first', 'enable-before-reopen'] as $ordering) {
        [$group, $admin, , $period, $session] = a04Fixture();
        $session->delete(); // Fixture preparation only; no academic activity was created.
        $period->update(['ends_on' => now('America/El_Salvador')->subDay()->toDateString()]);
        $group->cycle->update(['ends_on' => $period->ends_on]);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspender fixture cerrado coherente');
        if (str_contains($ordering, 'reopen')) {
            app(ClosureWorkflow::class)->closeCycle($admin, $group->cycle);
        }
        $first = match ($ordering) {
            'enable-first', 'enable-before-reopen' => fn () => app(OfferingLifecycle::class)->setActive($admin, $group, true, 'Habilitar antes de operación del ciclo'),
            'close-first' => fn () => app(ClosureWorkflow::class)->closeCycle($admin, $group->cycle),
            default => fn () => app(ClosureWorkflow::class)->reopenCycle($admin, $group->cycle, 'Reabrir antes de habilitar grupo'),
        };
        $operation = match ($ordering) {
            'enable-first' => 'close-cycle', 'enable-before-reopen' => 'reopen-cycle', default => 'enable'
        };
        $result = a04Race($first, ['operation' => $operation, 'actor' => $admin->id, 'group' => $group->id]);
        $review = str_contains($ordering, 'reopen');
        a04Check($result['outcome'] === 'ok' && $group->fresh()->active && $group->cycle->fresh()->status === ($review ? 'active' : 'closed')
            && $period->fresh()->state === 'closed' && $period->reports()->count() === 1
            && Offering::current()->whereKey($group->id)->exists() === $review, $ordering.': clasificación coherente y snapshot conservado');
    }
} finally {
    // Remove only generated fixtures, never reset the database or touch development.
    foreach ($groups as $fixture) {
        DB::transaction(function () use ($fixture): void {
            $sessions = DB::table('class_sessions')->where('offering_id', $fixture->id)->pluck('id');
            $periods = DB::table('assessment_periods')->where('offering_id', $fixture->id)->pluck('id');
            DB::table('assessment_periods')->whereIn('id', $periods)->update(['current_report_id' => null]);
            DB::table('report_versions')->whereIn('period_id', $periods)->delete();
            DB::table('attendance_events')->whereIn('session_id', $sessions)->delete();
            DB::table('absence_requests')->where('offering_id', $fixture->id)->delete();
            DB::table('attendances')->where('offering_id', $fixture->id)->delete();
            DB::table('class_sessions')->whereIn('id', $sessions)->delete();
            DB::table('assessment_periods')->whereIn('id', $periods)->delete();
            DB::table('enrollments')->where('offering_id', $fixture->id)->delete();
            DB::table('offerings')->where('id', $fixture->id)->delete();
            DB::table('subjects')->where('id', $fixture->subject_id)->delete();
            DB::table('academic_cycles')->where('id', $fixture->cycle_id)->delete();
        });
    }
    DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $createdUsers)->delete();
    DB::table('audit_logs')->whereIn('actor_id', $createdUsers)->delete();
    DB::table('users')->whereIn('id', $createdUsers)->delete();
}
