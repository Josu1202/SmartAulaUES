<?php

return [
    'name' => 'SmartAulaUES',
    'version' => '0.3.0',
    'stage' => 'Fase 2 · Clases, asistencia y permisos',
    'display_timezone' => env('SMARTAULAUES_DISPLAY_TIMEZONE', env('SIAA_DISPLAY_TIMEZONE', 'America/El_Salvador')),
    'demo_enabled' => (bool) env('DEMO_SEED', false),
    'demo_password' => env('DEMO_PASSWORD'),
];
