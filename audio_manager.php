<?php
// audio_manager.php - Gestione audio di sottofondo e funzioni correlate

/**
 * Catalogo di audio di sottofondo gratuiti disponibili online
 */
function getAudioCatalog() {
    return [
        'emozionale' => [
            [
                'name' => 'Hopeful Inspiring Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/01/18/audio_b1a7a5e662.mp3',
                'duration' => 161,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Emotional Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/01/26/audio_d0c6ff3b1d.mp3',
                'duration' => 149,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Beautiful Emotional Piano',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/15/audio_d0ce44a856.mp3',
                'duration' => 141,
                'credits' => 'Pixabay'
            ]
        ]
    ];
}

/**
 * Scarica una traccia audio da URL e la salva nella directory specificata
 */
function downloadAudioTrack($url, $savePath) {
    $audioContent = file_get_contents($url);
    if ($audioContent === false) return false;
    file_put_contents($savePath, $audioContent);
    return file_exists($savePath);
}

/**
 * Unisce audio di sottofondo a un video
 */
function mergeBackgroundAudio($videoPath, $audioPath, $outputPath) {
    $cmd = sprintf('%s -y -i "%s" -i "%s" -filter_complex "[0:a][1:a]amix=inputs=2:duration=shortest" -c:v copy -shortest "%s"',
                   FFMPEG_PATH, $videoPath, $audioPath, $outputPath);
    shell_exec($cmd);
    return file_exists($outputPath);
}
