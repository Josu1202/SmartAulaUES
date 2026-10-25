<?php

// First installation only. Existing secrets and settings are never rewritten.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$path = dirname(__DIR__).'/.env';
if (file_exists($path)) {
    echo ".env existente conservado, incluida APP_KEY.\n";
    exit(0);
}
$settings = ['APP_NAME' => 'SmartAulaUES', 'APP_ENV' => 'local', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => 'http://127.0.0.1:8085',
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55433', 'DB_DATABASE' => 'smartaulaues_local',
    'DB_PASSWORD' => bin2hex(random_bytes(24)), 'DEMO_SEED' => 'false', 'DEMO_PASSWORD' => '',
    'PGADMIN_PORT' => '5051', 'PGADMIN_PASSWORD' => bin2hex(random_bytes(24))];
$contents = file_get_contents(dirname(__DIR__).'/.env.example');
foreach ($settings as $name => $value) {
    $contents = preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $name.'='.$value, $contents);
}
$handle = fopen($path, 'x'); // Fail rather than overwrite a simultaneously created file.
fwrite($handle, $contents);
fclose($handle);
chmod($path, 0600);
echo ".env local creado con claves privadas nuevas. No se muestran en la salida.\n";
