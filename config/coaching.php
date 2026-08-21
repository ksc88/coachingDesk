<?php

return [
    'min_app_version' => env('COACHING_MIN_APP_VERSION', '1.0.0'),
    'default_timezone' => 'Asia/Kolkata',
    'display_date_format' => 'd-m-Y',
    'default_fee_due_day' => 5,
    'receipt_fy_start_month' => 4,

    'alerts' => [
        'default_mode' => env('COACHING_ALERTS_MODE', 'safe'),
        'max_attempts' => 5,
    ],
];
