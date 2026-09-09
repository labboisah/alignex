<?php

return [
    'pilot_node' => env('ADAPTIVE_PILOT_NODE', 'node'),
    'pilot_emergency_stop' => env('ADAPTIVE_PILOT_EMERGENCY_STOP', false),
    'engine' => [
        'shadow_enabled' => env('ADAPTIVE_ENGINE_SHADOW_ENABLED', false),
        'url' => env('ADAPTIVE_ENGINE_URL', 'http://127.0.0.1:8096'),
        'secret' => env('ADAPTIVE_ENGINE_SECRET', ''),
    ],
    // Owner and exam delivery approvals are stored in adaptive_pilot_controls.
];
