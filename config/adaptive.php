<?php

return [
    // Explicit online diagnostic cohorts only. Leave disabled until local pilot acceptance.
    'pilot_enabled' => env('ADAPTIVE_PILOT_ENABLED', false),
    'pilot_exams' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADAPTIVE_PILOT_EXAMS', ''))))),
    'pilot_owners' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADAPTIVE_PILOT_OWNERS', ''))))),
];
