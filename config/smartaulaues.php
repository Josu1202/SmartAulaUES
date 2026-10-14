<?php

return [
    'name' => 'SmartAulaUES',
    'version' => '0.3.2',
    'stage' => 'Fase 2 · Clases, asistencia y permisos',
    'display_timezone' => env('SMARTAULAUES_DISPLAY_TIMEZONE', env('SIAA_DISPLAY_TIMEZONE', 'America/El_Salvador')),
    'trusted_proxies' => env('SMARTAULAUES_TRUSTED_PROXIES', ''),
    'demo_enabled' => (bool) env('DEMO_SEED', false),
    'demo_password' => env('DEMO_PASSWORD'),
];
