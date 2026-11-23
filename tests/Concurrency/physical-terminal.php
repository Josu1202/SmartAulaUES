<?php

/** Concurrent writers against the isolated native PostgreSQL suite only. */
use App\Actions\Attendance\PhysicalTerminalWorkflow;
use App\Actions\Attendance\PortalManager;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'pgsql'
    || DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test'
    || DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test') {
    throw new RuntimeException('Se requieren ambas conexiones efectivas a testing/siaa_test.');
}

if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, 'READY '.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL);
    try {
        $result = match ($job['operation']) {
            'assign' => app(PhysicalTerminalWorkflow::class)->assign(User::findOrFail($job['actor']), ClassSession::findOrFail($job['session']), $job['data']),
            'qr' => app(PhysicalTerminalWorkflow::class)->recordQr(User::findOrFail($job['actor']), $job['data']),
        };
        fwrite(STDOUT, ($result['body']['status'] ?? 'assigned').PHP_EOL);
    } catch (HttpExceptionInterface $e) {
        fwrite(STDOUT, 'HTTP '.$e->getStatusCode().PHP_EOL);
    }

    exit;
}

function concurrentTerminalWrite(callable $first, array $job): string
{
    $worker = new Process([PHP_BINARY, __FILE__, '--env=testing', '--worker'], base_path(), timeout: 45);
    try {
        DB::transaction(function () use ($first, $job, $worker): void {
            $first();
            $worker->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $worker->start();
            $deadline = microtime(true) + 30;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                if (preg_match('/READY (\d+)/', $worker->getOutput(), $match)
                    && DB::selectOne("select count(*) as n from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [(int) $match[1]])->n > 0) {
                    return;
                }
                usleep(10000);
            } while ($worker->isRunning() && microtime(true) < $deadline);

            throw new RuntimeException('No se comprobó un bloqueo entre conexiones PostgreSQL. '.$worker->getErrorOutput());
        });
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException($worker->getErrorOutput());
        }
        $lines = preg_split('/\R/', trim($worker->getOutput()));

        return end($lines);
    } finally {
        if ($worker->isRunning()) {
            $worker->stop(1);
        }
    }
}

$workflow = app(PhysicalTerminalWorkflow::class);
$portals = app(PortalManager::class);
$coordinator = User::factory()->coordinator()->create();
$device = $workflow->provision($coordinator, ['name' => 'Carrera terminal física', 'capabilities' => ['qr']]);
DB::table('physical_terminals')->where('id', $device['id'])->update(['last_seen_at' => now()]);
$session = ClassSession::factory()->create([
    'starts_at' => now()->subMinute(), 'ends_at' => now()->addMinutes(20), 'modality' => 'online', 'state' => 'in_progress',
]);
$teacher = $session->offering->teacher;
$portal = $portals->open($teacher, $session, ['qr'], 10)['portal'];
$data = ['terminal_id' => $device['id'], 'portal_id' => $portal->id, 'methods' => ['qr']];
$outcome = concurrentTerminalWrite(fn () => $workflow->assign($teacher, $session, $data), [
    'operation' => 'assign', 'actor' => $teacher->id, 'session' => $session->id, 'data' => $data,
]);
if ($outcome !== 'HTTP 409' || DB::table('terminal_assignments')->where('terminal_id', $device['id'])->whereNull('released_at')->count() !== 1) {
    throw new RuntimeException('La terminal tiene más de una asignación o la segunda no fue rechazada.');
}
fwrite(STDOUT, 'PASS asignación física exclusiva bajo concurrencia'.PHP_EOL);

$student = Enrollment::factory()->create(['offering_id' => $session->offering_id, 'active_from' => '2026-01-01'])->student;
$assignment = DB::table('terminal_assignments')->where('terminal_id', $device['id'])->value('id');
$qr = DB::transaction(fn () => $workflow->rotateChallenge($workflow->terminal($device['id']), $assignment));
$packet = ['event_id' => (string) Str::uuid(), 'short_token' => $qr['token'], 'challenge_id' => $qr['challenge_id'], 'portal_id' => $portal->id];
$outcome = concurrentTerminalWrite(fn () => $portals->close($teacher, $portal), [
    'operation' => 'qr', 'actor' => $student->id, 'data' => $packet,
]);
if ($outcome !== 'rejected' || DB::table('attendances')->where('session_id', $session->id)->exists()) {
    throw new RuntimeException('Un QR corto registró después de que el portal cerró bajo locks.');
}
fwrite(STDOUT, 'PASS cierre concurrente invalida QR corto'.PHP_EOL);
