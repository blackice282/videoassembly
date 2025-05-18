<?php
// config.php

// Configurazione sistema VideoAssembly
$CONFIG = [
    'paths' => [
        'uploads' => 'uploads',
        'temp' => 'temp',
        'output' => 'output'
    ],
    'ffmpeg' => [
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'video_quality' => '23',
        'resolution' => '720x1280',
    ],
    'detection' => [
        'min_duration' => 1,
        'max_gap' => 2,
        'frame_rate' => 1,
        'confidence' => 0.5,
    ],
    'transitions' => [
        'enabled' => true,
        'type' => 'fade',
        'duration' => 0.5,
    ],
    'system' => [
        'cleanup_temp' => true,
        'keep_original' => true,
        'max_upload_size' => 500,
        'base_url' => 'https://videoassembly-ok.onrender.com',
        'debug' => false,
    ]
];

// Funzione per ottenere configurazione
function getConfig($key, $default = null) {
    global $CONFIG;
    $keys = explode('.', $key);
    $value = $CONFIG;
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    return $value;
}

// Funzione per impostare configurazione
function setConfig($key, $value) {
    global $CONFIG;
    $keys = explode('.', $key);
    $lastKey = array_pop($keys);
    $current = &$CONFIG;
    foreach ($keys as $k) {
        if (!isset($current[$k])) {
            $current[$k] = [];
        }
        $current = &$current[$k];
    }
    $current[$lastKey] = $value;
}
?>