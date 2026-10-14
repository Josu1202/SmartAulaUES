<?php

/** Independent PHP processes against an already migrated, dedicated PostgreSQL test database. */
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\AttendancePortal;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\NfcCredential;
use App\Models\Offering;
use App\Models\ReaderSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql' || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Usa APP_ENV=testing y una base PostgreSQL exclusiva con sufijo _test. El script no ejecuta migrate:fresh.');
}

if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, "READY\n");
    $deadline = microtime(true) + 20;
    while (! is_file($job['barrier'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('La barrera de concurrencia expiró.');
        }
        usleep(10000);
        clearstatcache(true, $job['barrier']);
    }
    try {
        $actor = User::findOrFail($job['actor']);
        $session = ClassSession::findOrFail($job['session']);
        $result = $job['operation'] === 'finish'
            ? ['http' => 200, 'body' => ['state' => app(SessionWorkflow::class)->transition($actor, $session, 'finish', 'Finalización concurrente de prueba.')->state->value]]
            : app(RegisterAttendance::class)->record($actor, $session, $job['method'], $job['data']);
    } catch (HttpExceptionInterface $exception) {
        $result = ['http' => $exception->getStatusCode()];
    } catch (Throwable $exception) {
        fwrite(STDOUT, json_encode(['error' => $exception::class]).PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
}

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function race(array $jobs, string $directory): array
{
    DB::disconnect();
    $barrier = $directory.'/'.Str::uuid();
    $connection = config('database.connections.pgsql');
    $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_SSLMODE' => $connection['sslmode'] ?? 'prefer', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'DEMO_SEED' => 'false'];
    $processes = [];
    try {
        foreach ($jobs as $job) {
            $process = new Process([PHP_BINARY, __FILE__, '--worker', '--env=testing'], dirname(__DIR__, 2), $environment, timeout: 30);
            $process->setInput(json_encode([...$job, 'barrier' => $barrier], JSON_THROW_ON_ERROR));
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 20;
        while (array_filter($processes, fn ($process) => ! str_contains($process->getOutput(), "READY\n"))) {
            foreach ($processes as $process) {
                verify($process->isRunning(), 'Un proceso no pudo iniciar; revisa el entorno de pruebas.');
            }
            verify(microtime(true) < $deadline, 'Los procesos no llegaron a la barrera.');
            usleep(10000);
        }
        file_put_contents($barrier, 'go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            verify($process->isSuccessful(), 'Un proceso falló durante el registro; salida: '.$process->getOutput());
            $results[] = json_decode(trim(substr($process->getOutput(), strlen("READY\n"))), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        if (is_file($barrier)) {
            unlink($barrier);
        }
    }
}

$directory = sys_get_temp_dir().'/smartaulaues-race-'.bin2hex(random_bytes(12));
mkdir($directory, 0700);
$cycle = $subject = $offering = $session = null;
$users = [];
try {
    $teacher = User::factory()->teacher()->create();
    $users[] = $teacher->id;
    $students = [];
    for ($i = 0; $i < 5; $i++) {
        $students[] = User::factory()->create();
        $users[] = $students[$i]->id;
    }
    $day = now(config('smartaulaues.display_timezone'))->subDay()->toDateString();
    $end = now(config('smartaulaues.display_timezone'))->addDay()->toDateString();
    $cycle = AcademicCycle::factory()->create(['starts_on' => $day, 'ends_on' => $end, 'status' => 'active']);
    $subject = Subject::factory()->create();
    $offering = Offering::factory()->create(['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'capacity' => 10]);
    $period = AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'starts_on' => $day, 'ends_on' => $end]);
    $session = ClassSession::factory()->create(['offering_id' => $offering->id, 'period_id' => $period->id, 'state' => 'in_progress', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addMinutes(30)]);
    foreach ($students as $student) {
        Enrollment::factory()->create(['offering_id' => $offering->id, 'student_id' => $student->id, 'active_from' => $day]);
    }
    $portal = app(PortalManager::class)->open($teacher, $session, ['nfc', 'link', 'manual'], 15);
    $reader = app(PortalManager::class)->reader($teacher, $portal['portal'], 'Lector de prueba concurrente');
    $token = 'SIAA:C1:'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    NfcCredential::create(['user_id' => $students[1]->id, 'token_hash' => hash('sha256', $token), 'state' => 'active', 'activated_at' => now()]);
    $manual = fn ($student, $event) => ['operation' => 'record', 'actor' => $teacher->id, 'session' => $session->id, 'method' => 'manual', 'data' => ['event_id' => $event, 'portal_id' => $portal['portal']->id, 'student_id' => $student->id, 'reason' => 'Registro concurrente de prueba.']];

    $event = (string) Str::uuid();
    $results = race([$manual($students[0], $event), $manual($students[0], $event)], $directory);
    $codes = array_column($results, 'http');
    sort($codes);
    verify($codes === [200, 201] && AttendanceEvent::whereKey($event)->count() === 1 && Attendance::where('session_id', $session->id)->count() === 1, 'UUID repetido creó un resultado inconsistente.');
    fwrite(STDOUT, "PASS mismo UUID/cuerpo simultáneo: una fila y una respuesta repetida\n");

    $results = race([
        $manual($students[1], (string) Str::uuid()),
        ['operation' => 'record', 'actor' => $teacher->id, 'session' => $session->id, 'method' => 'nfc', 'data' => ['event_id' => (string) Str::uuid(), 'reader_session_id' => $reader->id, 'credential_token' => $token]],
        ['operation' => 'record', 'actor' => $students[1]->id, 'session' => $session->id, 'method' => 'link', 'data' => ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'portal_token' => $portal['token']]],
    ], $directory);
    $codes = array_column($results, 'http');
    sort($codes);
    verify($codes === [200, 200, 201] && Attendance::where('session_id', $session->id)->count() === 2 && AttendanceEvent::where('session_id', $session->id)->count() === 4, 'Métodos simultáneos duplicaron la presencia.');
    fwrite(STDOUT, "PASS NFC/enlace/manual simultáneos: una presencia por inscripción\n");

    $event = (string) Str::uuid();
    $results = race([$manual($students[2], $event), $manual($students[3], $event)], $directory);
    $codes = array_column($results, 'http');
    sort($codes);
    verify($codes === [201, 409] && AttendanceEvent::whereKey($event)->count() === 1 && Attendance::where('session_id', $session->id)->count() === 3, 'UUID con cuerpos distintos no devolvió conflicto.');
    fwrite(STDOUT, "PASS mismo UUID/cuerpos distintos: una escritura y un conflicto\n");

    $results = race([$manual($students[4], (string) Str::uuid()), ['operation' => 'finish', 'actor' => $teacher->id, 'session' => $session->id]], $directory);
    verify(in_array($results[0]['http'], [201, 409], true) && $results[1]['http'] === 200 && $session->fresh()->state->value === 'completed' && Attendance::where('session_id', $session->id)->count() === 5, 'Finalización concurrente dejó resultados incompletos.');
    verify(AttendancePortal::where('session_id', $session->id)->whereNull('closed_at')->count() === 0 && ReaderSession::where('portal_id', $portal['portal']->id)->where('state', 'active')->count() === 0, 'La clase finalizada dejó un lector/portal abierto.');
    fwrite(STDOUT, "PASS registro/finalización simultáneos: cinco resultados y portales cerrados\n");
} finally {
    // Delete only fixture IDs created by this process; preserve all other test database rows.
    DB::transaction(function () use ($users, $cycle, $subject, $offering, $session): void {
        if ($session) {
            AttendanceEvent::where('session_id', $session->id)->delete();
            Attendance::where('session_id', $session->id)->delete();
            ReaderSession::whereIn('portal_id', AttendancePortal::where('session_id', $session->id)->select('id'))->delete();
            AttendancePortal::where('session_id', $session->id)->delete();
            $session->delete();
        }
        if ($offering) {
            Enrollment::where('offering_id', $offering->id)->delete();
            AssessmentPeriod::where('offering_id', $offering->id)->delete();
            $offering->delete();
        }
        $subject?->delete();
        $cycle?->delete();
        DB::table('audit_logs')->whereIn('actor_id', $users)->delete();
        NfcCredential::whereIn('user_id', $users)->delete();
        User::whereIn('id', $users)->delete();
    });
    rmdir($directory);
}
