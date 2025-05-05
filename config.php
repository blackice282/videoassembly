<?php
// config.php

$CONFIG = [
    // Percorsi assoluti alle cartelle (verranno create automaticamente)
    'paths' => [
        'uploads' => __DIR__ . '/uploads',
        'temp'    => __DIR__ . '/temp',
        'output'  => __DIR__ . '/output',
    ],

    // Parametri FFmpeg
    'ffmpeg' => [
        'video_codec'   => 'libx264',
        'audio_codec'   => 'aac',
        'video_quality' => '23',   // CRF
    ],

    // Sistema
    'system' => [
        'max_upload_size' => 200,  // MB per file
    ],
];

/**
 * Restituisce una chiave di configurazione (es. 'paths.uploads')
 */
function getConfig(string $key, $default = null) {
    global $CONFIG;
    $parts = explode('.', $key);
    $v = $CONFIG;
    foreach ($parts as $p) {
        if (is_array($v) && array_key_exists($p, $v)) {
            $v = $v[$p];
        } else {
            return $default;
        }
    }
    return $v;
}
