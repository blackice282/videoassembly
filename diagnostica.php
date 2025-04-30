<?php
require_once 'config.php';

/**
 * Ritorna un audio casuale da una categoria
 */
function getRandomAudioFromCategory($category) {
    $catalog = [
        'emozionale' => [
            [
                'name' => 'SoundHelix Song 1',
                'url'  => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3'
            ],
            [
                'name' => 'SoundHelix Song 2',
                'url'  => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3'
            ]
        ]
    ];

    return $catalog[$category][array_rand($catalog[$category])] ?? null;
}

/**
 * Scarica l'audio da un URL usando cURL
 */
function downloadAudio($url, $path) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (PHP VideoAssembly)',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $data = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http === 200 && $data !== false) {
        file_put_contents($path, $data);
        return file_exists($path) && filesize($path) > 0;
    }
    return false;
}

/**
 * Applica un audio di sottofondo a un video
 *
 * @param string $videoPath
 * @param string $audioPath
 * @param string $outputPath
 * @param float  $volume
 * @return bool
 */
function applyBackgroundAudio($videoPath, $audioPath, $outputPath, $volume = 0.3) {
    $cmd = sprintf(
        '%s -y -i %s -i %s -filter_complex "[1:a]volume=%f[a1];[0:a][a1]amix=inputs=2:duration=first:dropout_transition=2[aout]" -map 0:v -map "[aout]" -c:v copy -shortest %s',
        FFMPEG_PATH,
        escapeshellarg($videoPath),
        escapeshellarg($audioPath),
        $volume,
        escapeshellarg($outputPath)
    );
    shell_exec($cmd);
    return file_exists($outputPath) && filesize($outputPath) > 0;
}
