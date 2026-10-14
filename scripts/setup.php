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
    [PHP_BINARY, 'artisan', 'migrate', '--seed', '--no-interaction'],
    ['npm', 'ci'], ['npm', 'run', 'build'],
];
// Setup is also used on an existing checkout. Never rotate an installed encryption key.
$environment = file_get_contents('.env');
if (! preg_match('/^APP_KEY=.+$/m', $environment)) {
    array_splice($commands, 1, 0, [[PHP_BINARY, 'artisan', 'key:generate', '--no-interaction']]);
}
foreach ($commands as $command) {
    fwrite(STDOUT, '> '.implode(' ', $command).PHP_EOL);
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('No se completó: '.implode(' ', $command));
    }
}
fwrite(STDOUT, "Instalación completa. Inicia con: php artisan serve --port=8080\n");
