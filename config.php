<?php
return [
    'paths' => [
        'upload_dir'   => __DIR__ . '/uploads/',
        'temp_dir'     => __DIR__ . '/temp/',
        'output_dir'   => __DIR__ . '/output/',
    ],

    'system' => [
        'max_upload_size' => 200 * 1024 * 1024, // 200MB
        'base_url'        => 'https://your-app-name.onrender.com',
    ],

    'codec' => [
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'preset'      => 'medium',
        'crf'         => 23,
    ],

    'detection' => [
        'min_duration' => 1.5,    // seconds
        'max_gap'      => 0.5,    // seconds
        'frame_rate'   => 24,     // fps
        'confidence'   => 0.25,   // threshold
    ],

    'transitions' => [
        'enabled'    => true,
        'type'       => 'fade',   // fade, dissolve, wipe
        'duration'   => 1.0,      // seconds
    ],
];
?>