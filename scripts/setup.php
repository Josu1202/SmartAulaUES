<?php

/** Run after configuring .env and starting a reachable PostgreSQL database. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (PHP_VERSION_ID < 80400) {
    throw new RuntimeException('Se requiere PHP 8.4 o superior.');
}
$root = dirname(__DIR__);
chdir($root);
if (! file_exists('.env')) {
    throw new RuntimeException('Ejecuta primero: php scripts/configure.php.');
}
$commands = [
    ['composer', 'install', '--no-interaction'],
    [PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'],
    [PHP_BINARY, 'artisan', 'migrate', '--seed', '--no-interaction'],
    ['npm', 'ci'], ['npm', 'run', 'build'],
];
foreach ($commands as $command) {
    fwrite(STDOUT, '> '.implode(' ', $command).PHP_EOL);
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('No se completó: '.implode(' ', $command));
    }
}
fwrite(STDOUT, "Instalación completa. Inicia con: php artisan serve --port=8080\n");
