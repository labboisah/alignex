<?php

return [
    // These controls prepare a restricted pilot; they do not enable an unfinished engine.
    'pilot_enabled' => env('ADAPTIVE_PILOT_ENABLED', false),
    'pilot_owners' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADAPTIVE_PILOT_OWNERS', ''))))),
];
