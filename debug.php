<?php
require_once 'config.php';

/**
 * Verifica lo stato di un file video/audio
 *
 * @param string $filePath Percorso del file da verificare
 * @return array Risultato della verifica
 */
function checkMediaFile($filePath) {
    if (!file_exists($filePath)) {
        return [
            'exists' => false,
            'message' => 'File non trovato'
        ];
    }
    $size = filesize($filePath);
    $duration = 0;
    $output = shell_exec(FFMPEG_PATH . ' -i ' . escapeshellarg($filePath) . ' 2>&1');
    if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $output, $m)) {
        $duration = $m[1] * 3600 + $m[2] * 60 + $m[3];
    }
    return [
        'exists' => true,
        'size' => $size,
        'duration' => $duration
    ];
}

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
        'codecs' => $codecs,
        'filters' => $filters
    ];
}

// Se eseguito via browser, mostra risultati in JSON
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode(checkFFmpegCapabilities(), JSON_PRETTY_PRINT);
}
?>
