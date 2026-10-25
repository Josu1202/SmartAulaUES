<?php

/** Exercise the A04 → A05 schema transition inside a rollback-only PostgreSQL transaction. */
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test'
    || DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test') {
    throw new RuntimeException('La sonda de migración requiere testing y siaa_test efectivos.');
}
DB::beginTransaction();
try {
    $user = User::factory()->create(['study_year' => 5, 'entry_year' => 2021]);
    $passwordHash = $user->password;
    $migration = require database_path('migrations/2026_10_24_000100_complete_a05_integration.php');
    $migration->down();
    DB::table('users')->where('id', $user->id)->update(['study_year' => 6]);
    try {
        $migration->up();
        throw new LogicException('La migración no debe interpretar sexto año como Egresado.');
    } catch (RuntimeException $exception) {
        if (! str_contains($exception->getMessage(), 'clasifica explícitamente')) {
            throw $exception;
        }
    }
    if (Schema::hasColumn('users', 'graduated')) {
        throw new LogicException('El preflight dejó cambios parciales.');
    }
    DB::table('users')->where('id', $user->id)->update(['study_year' => 5]);
    $migration->up();
    $upgraded = User::findOrFail($user->id);
    if ($upgraded->study_year !== 5 || $upgraded->graduated || $upgraded->must_change_password || $upgraded->password !== $passwordHash) {
        throw new LogicException('La migración alteró una cuenta anterior.');
    }
    fwrite(STDOUT, "PASS A04 → A05: cuenta/contraseña/años conservados; año 6 bloqueado sin cambios parciales; rollback de toda la sonda.\n");
} finally {
    DB::rollBack();
}
