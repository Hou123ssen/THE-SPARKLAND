<?php

return [
    'original_disk' => env('MEDIA_ORIGINAL_DISK', 'local'),
    'derivative_disk' => env('MEDIA_DERIVATIVE_DISK', 'public'),
    'max_upload_mb' => (int) env('MEDIA_MAX_UPLOAD_MB', 12),
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 40_000_000),
    'queue' => env('MEDIA_QUEUE', 'default'),
    'responsive_widths' => [320, 480, 768, 960, 1440],
    'presets' => [
        'responsive_padding_ratio' => 0.05,
        'device_browser' => ['width' => 960, 'height' => 540, 'padding_ratio' => 0.12],
    ],
];
