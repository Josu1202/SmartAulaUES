<?php

return [
    'name' => 'SmartAulaUES',
    'version' => '0.1.2',
    'stage' => 'Base del proyecto',
    'display_timezone' => env('SMARTAULAUES_DISPLAY_TIMEZONE', env('SIAA_DISPLAY_TIMEZONE', 'America/El_Salvador')),
    'demo_enabled' => (bool) env('DEMO_SEED', false),
    'demo_password' => env('DEMO_PASSWORD'),
];
