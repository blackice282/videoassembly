<?php
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('TEMP_DIR', __DIR__ . '/temp');
define('FFMPEG_PATH', '/usr/bin/ffmpeg');

function getConfig($key, $default = null) {
    $config = [
        'paths' => [
            'uploads' => UPLOAD_DIR,
            'temp' => TEMP_DIR
        ],
        'system' => [
            'cleanup_temp' => true
        ]
    ];
    $keys = explode('.', $key);
    $value = $config;
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    return $value;
}
?>