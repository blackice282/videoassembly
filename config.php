<?php
// Configurazioni principali
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('TEMP_DIR',   __DIR__ . '/temp');
define('FFMPEG_PATH','/usr/bin/ffmpeg');
define('PRIVACY_LOG', __DIR__ . '/privacy_log.json');

// Crea le cartelle se non esistono
foreach ([UPLOAD_DIR, TEMP_DIR] as $d) {
    if (!file_exists($d)) mkdir($d, 0777, true);
}

function getConfig($key, $default = null) {
    $config = [
        'paths' => [
            'uploads'     => UPLOAD_DIR,
            'temp'        => TEMP_DIR,
            'privacy_log' => PRIVACY_LOG,
        ],
        'system' => [
            'cleanup_temp' => true,
            'debug'        => true,
        ],
        'privacy' => [
            'retention_hours' => 48,
            'track_files'     => true,
        ],
    ];
    $parts = explode('.', $key);
    $v = $config;
    foreach ($parts as $p) {
        if (isset($v[$p])) $v = $v[$p];
        else return $default;
    }
    return $v;
}

function setConfig($key, $value) {
    // non implementato
}
