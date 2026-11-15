<?php

/*
|--------------------------------------------------------------------------
| RekaCy business configuration
|--------------------------------------------------------------------------
| Every rule that a lecturer may ask to change during the code interview
| (delay thresholds, group scenario) lives here and is driven by .env.
*/
return [
    // Positions in the seeded dataset are SIMULATED. Maps show a label while this is true.
    'demo_data' => (bool) env('REKACY_DEMO_DATA', true),

    'timezone' => env('REKACY_TIMEZONE', 'Asia/Jakarta'),

    // Unique group scenario (documented in README).
    'group' => [
        'name' => env('REKACY_GROUP', 'Group A'),
        'route' => env('REKACY_GROUP_ROUTE', 'Southeast Asia → Europe'),
        'main_exception' => env('REKACY_GROUP_EXCEPTION', 'CUSTOMS_HOLD'),
        'kpi' => env('REKACY_GROUP_KPI', 'On-time delivery rate'),
    ],

    // Deterministic delay rule: delay = (ETA or delivery time) - expected arrival.
    //   delay <= 0                      -> not delayed
    //   0 < delay <= low_max_hours      -> LOW
    //   low_max < delay <= medium_max   -> MEDIUM
    //   delay > medium_max_hours        -> HIGH
    'delay' => [
        'low_max_hours' => (float) env('DELAY_LOW_MAX_HOURS', 6),
        'medium_max_hours' => (float) env('DELAY_MEDIUM_MAX_HOURS', 24),
        // Group threshold: delays at/above this value are flagged "escalated" and
        // the whole operations team is notified.
        'escalate_hours' => (float) env('DELAY_THRESHOLD_HOURS', 12),
    ],

    'tracking' => [
        // Events may not be dated further in the future than this (minutes).
        'future_tolerance_minutes' => 5,
    ],

    'cargo_types' => [
        'General cargo', 'Coffee beans', 'Textiles', 'Spices', 'Palm-oil derivatives',
        'Electronics', 'Furniture', 'Rubber products', 'Seafood (reefer)', 'Machinery',
    ],

    'notifications' => [
        // Email is an advanced feature: needs a configured MAIL_* driver.
        'mail' => (bool) env('REKACY_MAIL_NOTIFICATIONS', false),
    ],

    'geocoding' => [
        'url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org/search'),
        // Nominatim usage policy requires an identifying User-Agent.
        'user_agent' => env('GEOCODING_USER_AGENT', 'RekaCy-UAS/1.0 (student project)'),
        'timeout' => (int) env('GEOCODING_TIMEOUT', 5),
    ],

    'weather' => [
        'url' => env('WEATHER_URL', 'https://api.open-meteo.com/v1/forecast'),
        'timeout' => (int) env('WEATHER_TIMEOUT', 5),
    ],
];
