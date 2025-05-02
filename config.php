<?php
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('TEMP_DIR',   __DIR__ . '/temp');
define('FFMPEG_PATH','/usr/bin/ffmpeg');

foreach ([UPLOAD_DIR, TEMP_DIR] as $d) {
    if (!file_exists($d)) mkdir($d, 0777, true);
}

function getConfig($key, $default = null) {
    $config = [
        'paths' => [
            'uploads' => UPLOAD_DIR,
            'temp'    => TEMP_DIR
        ],
        'system' => [
            'cleanup_temp' => true,
            'debug'        => true
        ]
    ];
    $parts = explode('.', $key);
    $v = $config;
    foreach ($parts as $p) {
        if (!isset($v[$p])) return $default;
        $v = $v[$p];
    }
    return $v;
}
?>
