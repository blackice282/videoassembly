<?php
require_once 'config.php';

/**
 * Verifica codec e filtri FFmpeg
 *
 * @return array Capacità supportate
 */
function checkFFmpegCapabilities() {
    $codecsOutput = shell_exec(FFMPEG_PATH . ' -codecs 2>&1');
    $filtersOutput = shell_exec(FFMPEG_PATH . ' -filters 2>&1');

    $codecs = [
        'libx264' => strpos($codecsOutput, 'libx264') !== false,
        'h264'    => strpos($codecsOutput, 'h264') !== false,
        'aac'     => strpos($codecsOutput, 'aac') !== false,
        'mp3'     => strpos($codecsOutput, 'mp3') !== false
    ];

    $filters = [
        'colorbalance' => strpos($filtersOutput, 'colorbalance') !== false,
        'unsharp'      => strpos($filtersOutput, 'unsharp') !== false,
        'hue'          => strpos($filtersOutput, 'hue') !== false,
        'eq'           => strpos($filtersOutput, 'eq') !== false
    ];

    return [
        'codecs'  => $codecs,
        'filters' => $filters
    ];
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode(checkFFmpegCapabilities(), JSON_PRETTY_PRINT);
}
