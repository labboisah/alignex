<?php

return [
    'pilot_node' => env('ADAPTIVE_PILOT_NODE', 'node'),
    'pilot_emergency_stop' => env('ADAPTIVE_PILOT_EMERGENCY_STOP', false),
    'engine' => [
        'shadow_enabled' => env('ADAPTIVE_ENGINE_SHADOW_ENABLED', false),
        'url' => env('ADAPTIVE_ENGINE_URL', 'http://127.0.0.1:8096'),
        'secret' => env('ADAPTIVE_ENGINE_SECRET', ''),
    ],
    // Explicit online diagnostic cohorts only. Leave disabled until local pilot acceptance.
    'pilot_enabled' => env('ADAPTIVE_PILOT_ENABLED', false),
    'pilot_exams' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADAPTIVE_PILOT_EXAMS', ''))))),
    'pilot_owners' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADAPTIVE_PILOT_OWNERS', ''))))),
];
