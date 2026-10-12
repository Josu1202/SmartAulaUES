<?php

return [
    'version' => '0.1.0',
    'stage' => 'Base del proyecto',
    'display_timezone' => env('SIAA_DISPLAY_TIMEZONE', 'America/El_Salvador'),
    'demo_enabled' => (bool) env('DEMO_SEED', false),
    'demo_password' => env('DEMO_PASSWORD'),
];
