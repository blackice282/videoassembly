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
                'url'  => 'https://cdn.pixabay.com/audio/2022/01/18/audio_b1a7a5e662.mp3'
            ],
            [
                'name' => 'Emotional Piano',
                'url'  => 'https://cdn.pixabay.com/audio/2022/01/26/audio_d0c6ff3b1d.mp3'
            ],
            [
                'name' => 'Beautiful Emotional Piano',
                'url'  => 'https://cdn.pixabay.com/audio/2022/03/15/audio_d0ce44a856.mp3'
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
 * @param string $videoPath Percorso del video di input
 * @param string $audioPath Percorso del file audio scaricato
 * @param string $outputPath Percorso del video di output
 * @param float  $volume Fattore volume [0.0–1.0]
 * @return bool true se successo, false altrimenti
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
