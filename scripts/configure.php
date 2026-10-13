<?php

/** Prepare local settings without overwriting an existing environment. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
if (file_exists($root.'/.env')) {
    fwrite(STDOUT, ".env ya existe; se conserva. Revisa DB_PASSWORD y DEMO_PASSWORD.\n");
    exit(0);
}
$template = file_get_contents($root.'/.env.example');
if ($template === false) {
    throw new RuntimeException('No se encontró .env.example.');
}
$databasePassword = bin2hex(random_bytes(16));
$demoPassword = 'SmartAulaUES-'.bin2hex(random_bytes(9)).'!';
$template = preg_replace('/^DB_PASSWORD=$/m', 'DB_PASSWORD='.$databasePassword, $template);
$template = preg_replace('/^DEMO_PASSWORD=$/m', 'DEMO_PASSWORD='.$demoPassword, $template);
file_put_contents($root.'/.env', $template);
chmod($root.'/.env', 0600);
fwrite(STDOUT, ".env local creado.\nContraseña de las cuentas ficticias: {$demoPassword}\n");
fwrite(STDOUT, "Si usas PostgreSQL local, adapta DB_HOST, DB_PORT, DB_USERNAME y DB_PASSWORD antes de instalar.\n");
