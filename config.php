<?php
return [
    'paths' => [
        'upload_dir'   => __DIR__ . '/uploads/',
        'temp_dir'     => __DIR__ . '/temp/',
        'output_dir'   => __DIR__ . '/output/',
    ],
    'system' => [
        'max_upload_size' => 200 * 1024 * 1024,
        'base_url'        => 'https://your-app-name.onrender.com',
    ],
    'codec' => [
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'preset'      => 'medium',
        'crf'         => 23,
    ],
    'detection' => [
        'min_duration' => 1.5,
        'max_gap'      => 0.5,
        'frame_rate'   => 24,
        'confidence'   => 0.25,
    ],
    'transitions' => [
        'enabled'    => true,
        'type'       => 'fade',
        'duration'   => 1.0,
    ],
];
?>