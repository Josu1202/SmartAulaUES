<?php

// Run inside the same Compose environment as the subsequent test command.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    if (! $app->environment('testing') || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'siaa_test') {
        throw new RuntimeException;
    }
    $connection = DB::connection();
    $writeDatabase = $connection->getPdo()->query('select current_database()')->fetchColumn();
    $readDatabase = $connection->getReadPdo()->query('select current_database()')->fetchColumn();
    if ($writeDatabase !== 'siaa_test' || $readDatabase !== 'siaa_test') {
        throw new RuntimeException;
    }
    fwrite(STDOUT, "PASS entorno testing; PostgreSQL; lectura y escritura efectivas: siaa_test\n");
} catch (Throwable) {
    fwrite(STDERR, "STOP: no se verificó una conexión exclusiva a siaa_test. No ejecutar pruebas.\n");
    exit(1);
}
