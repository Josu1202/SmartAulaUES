<?php

/** A05: independent native PostgreSQL processes, verified waits, no shared fixture deletion. */
use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\CsvImporter;
use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\NfcCredential;
use App\Models\Offering;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'siaa_test'
    || DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test'
    || DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test') {
    throw new RuntimeException('A05 requiere lectura y escritura efectivas exclusivamente en siaa_test.');
}

if (in_array('--worker', $argv, true)) {
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    fwrite(STDOUT, 'READY '.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL);
    try {
        $actor = User::findOrFail($job['actor']);
        match ($job['operation']) {
            'credential' => app(CredentialManager::class)->confirm($actor, NfcCredential::findOrFail($job['credential']), $job['token']),
            'csv' => app(CsvImporter::class)->confirm($actor, $job['preview']),
            'schedule' => app(SessionWorkflow::class)->save($actor, Offering::findOrFail($job['group']), $job['data']),
            'reassign' => app(AcademicRegistry::class)->saveOffering($actor, $job['data'], Offering::findOrFail($job['group'])),
        };
        $outcome = 'ok';
    } catch (ValidationException) {
        $outcome = 'blocked';
    }
    fwrite(STDOUT, $outcome.PHP_EOL);
    exit;
}

function a05Race(callable $first, array $job): string
{
    $worker = null;
    try {
        DB::transaction(function () use ($first, $job, &$worker): void {
            $first();
            $worker = new Process([PHP_BINARY, __FILE__, '--env=testing', '--worker'], base_path(), timeout: 60);
            $worker->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $worker->start();
            $deadline = microtime(true) + 45;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                if (preg_match('/READY (\d+)/', $worker->getOutput(), $match)
                    && DB::selectOne("select count(*) as n from pg_stat_activity where pid = ? and wait_event_type = 'Lock'", [(int) $match[1]])->n > 0) {
                    return;
                }
                if (! $worker->isRunning() || microtime(true) >= $deadline) {
                    throw new RuntimeException('No se verificó espera PostgreSQL independiente. '.$worker->getErrorOutput());
                }
                usleep(10000);
            } while (true);
        });
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException($worker->getErrorOutput());
        }
        $lines = preg_split('/\R/', trim($worker->getOutput()));

        return end($lines);
    } finally {
        if ($worker?->isRunning()) {
            $worker->stop(1);
        }
    }
}

function a05Check(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
    fwrite(STDOUT, 'PASS espera nativa PostgreSQL: '.$label.PHP_EOL);
}

$admin = User::factory()->coordinator()->create();
$student = User::factory()->create();
$groups = [];
$importEmail = 'a05-'.Str::uuid().'@example.test';
try {
    $manager = app(CredentialManager::class);
    $credential = $manager->issue($admin, $student);
    $outcome = a05Race(fn () => $manager->confirm($admin, $credential['credential'], $credential['token']), [
        'operation' => 'credential', 'actor' => $admin->id, 'credential' => $credential['credential']->id, 'token' => $credential['token'],
    ]);
    a05Check($outcome === 'ok' && $credential['credential']->fresh()->state === 'active'
        && AuditLog::where('entity_type', 'NfcCredential')->where('entity_id', (string) $credential['credential']->id)->where('action', 'credential.activated')->count() === 1,
        'confirmación NFC simultánea devuelve éxito sin duplicar activación');
    $outcome = a05Race(fn () => $manager->revoke($admin, $credential['credential'], 'Revocación concurrente A05'), [
        'operation' => 'credential', 'actor' => $admin->id, 'credential' => $credential['credential']->id, 'token' => $credential['token'],
    ]);
    a05Check($outcome === 'blocked' && $credential['credential']->fresh()->state === 'revoked', 'revocar contra reintento NFC no reactiva la credencial');

    $preview = ['kind' => 'teachers', 'offering_id' => null, 'rows' => [['line' => 2, 'fields' => ['name' => 'A05 concurrente', 'email' => $importEmail]]], 'password_hash' => Hash::make(Str::random(32))];
    $preview['baseline'] = app(CsvImporter::class)->baseline($preview);
    $outcome = a05Race(fn () => app(CsvImporter::class)->confirm($admin, $preview), ['operation' => 'csv', 'actor' => $admin->id, 'preview' => $preview]);
    a05Check($outcome === 'blocked' && User::where('email', $importEmail)->count() === 1, 'doble confirmación CSV produce una sola cuenta, sin sobrescribirla');

    $a = Offering::factory()->create();
    $b = Offering::factory()->create();
    $groups = [$a, $b];
    foreach ($groups as $group) {
        $group->cycle->update(['starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->addYear()->endOfYear()->toDateString()]);
    }
    $pa = AssessmentPeriod::factory()->create(['offering_id' => $a->id, 'starts_on' => now()->startOfYear(), 'ends_on' => now()->endOfYear()]);
    $pb = AssessmentPeriod::factory()->create(['offering_id' => $b->id, 'starts_on' => now()->startOfYear(), 'ends_on' => now()->endOfYear()]);
    $start = CarbonImmutable::now('America/El_Salvador')->addDay()->startOfHour();
    ClassSession::factory()->create(['offering_id' => $a->id, 'period_id' => $pa->id, 'starts_at' => $start->utc(), 'ends_at' => $start->addHour()->utc()]);
    $assignment = [...$a->only(['cycle_id', 'subject_id', 'capacity', 'group_code']), 'teacher_id' => $b->teacher_id, 'reason' => 'Reasignación concurrente A05'];
    $schedule = ['period_id' => $pb->id, 'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->addHour()->format('Y-m-d\TH:i'), 'modality' => 'online'];
    $outcome = a05Race(fn () => app(AcademicRegistry::class)->saveOffering($admin, $assignment, $a), [
        'operation' => 'schedule', 'actor' => $b->teacher_id, 'group' => $b->id, 'data' => $schedule,
    ]);
    a05Check($outcome === 'blocked' && $b->sessions()->count() === 0, 'reasignar contra programar comparte lock del docente y detecta el nuevo conflicto');
    app(AcademicRegistry::class)->saveOffering($admin, [...$assignment, 'teacher_id' => $a->teacher_id], $a->fresh());
    $outcome = a05Race(fn () => app(SessionWorkflow::class)->save($b->teacher, $b, $schedule), [
        'operation' => 'reassign', 'actor' => $admin->id, 'group' => $a->id, 'data' => $assignment,
    ]);
    a05Check($outcome === 'blocked' && $a->fresh()->teacher_id === $a->teacher_id, 'programar contra reasignar revalida el horario después de esperar');

    // A direct overlapping command must return promptly without overwriting health.
    $beforeRun = DB::table('operation_runs')->where('name', 'academic-closures')->first();
    DB::select("SELECT pg_advisory_lock(hashtextextended('smartaulaues:closures', 0))");
    try {
        $overlap = new Process([PHP_BINARY, 'artisan', 'attendance:process-closures', '--env=testing'], base_path(), timeout: 60);
        $overlap->mustRun();
        if (! str_contains($overlap->getOutput(), 'Ya existe un procesador')
            || json_encode($beforeRun) !== json_encode(DB::table('operation_runs')->where('name', 'academic-closures')->first())) {
            throw new RuntimeException('El comando solapado alteró el indicador o no reconoció el lock PostgreSQL.');
        }
    } finally {
        DB::select("SELECT pg_advisory_unlock(hashtextextended('smartaulaues:closures', 0))");
    }
    fwrite(STDOUT, 'PASS exclusión PostgreSQL: comando independiente duplicado termina sin procesar ni sobrescribir salud'.PHP_EOL);
} finally {
    DB::transaction(function () use ($groups, $admin, $student, $importEmail): void {
        foreach ($groups as $group) {
            $group->sessions()->delete();
            $group->periods()->delete();
            $group->delete();
            $group->cycle->delete();
            $group->subject->delete();
        }
        $users = [$admin->id, $student->id, ...array_map(fn ($g) => $g->teacher_id, $groups), ...User::where('email', $importEmail)->pluck('id')->all()];
        DB::table('nfc_credentials')->whereIn('user_id', $users)->delete();
        DB::table('audit_logs')->whereIn('actor_id', $users)->delete();
        User::whereIn('id', $users)->delete();
    });
}
