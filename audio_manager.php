<?php
require_once 'config.php';

/**
 * Ritorna un audio casuale da una categoria
 */
function getRandomAudioFromCategory($category) {
    $catalog = [
        'emozionale' => [
            [
                'name' => 'Hopeful Inspiring Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/01/18/audio_b1a7a5e662.mp3'
            ],
            [
                'name' => 'Emotional Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/01/26/audio_d0c6ff3b1d.mp3'
            ],
            [
                'name' => 'Beautiful Emotional Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/15/audio_d0ce44a856.mp3'
            ]
        ]
    ];

    if (!isset($catalog[$category])) return null;
    return $catalog[$category][array_rand($catalog[$category])];
}

/**
 * Scarica l'audio da un URL
 */
function downloadAudio($url, $path) {
    $content = file_get_contents($url);
    if ($content === false) return false;
    file_put_contents($path, $content);
    return file_exists($path);
}

/**
 * Applica un audio di sottofondo a un video
 */
function applyBackgroundAudio($videoPath, $audioPath, $outputPath, $volume = 0.3) {
    $cmd = sprintf('%s -y -i "%s" -i "%s" -filter_complex "[1:a]volume=%f[a1];[0:a][a1]amix=inputs=2:duration=first:dropout_transition=2[aout]" -map 0:v -map "[aout]" -c:v copy -shortest "%s"',
        FFMPEG_PATH, $videoPath, $audioPath, $volume, $outputPath);
    shell_exec($cmd);
    return file_exists($outputPath) && filesize($outputPath) > 0;
}
?>
