<?php
// audio_manager.php - Gestisce gli audio di sottofondo

/**
 * Catalogo di audio di sottofondo gratuiti disponibili online
 * Utilizzando risorse da librerie gratuite come Pixabay, Freesound, etc.
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
        ],
        'bambini' => [
            [
                'name' => 'Happy Kids',
                'url' => 'https://cdn.pixabay.com/audio/2021/10/25/audio_956cbddd19.mp3',
                'duration' => 125,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Children Song',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/15/audio_942d0c59c3.mp3',
                'duration' => 95,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Cute Children',
                'url' => 'https://cdn.pixabay.com/audio/2021/11/25/audio_359b6d2532.mp3',
                'duration' => 111,
                'credits' => 'Pixabay'
            ]
        ],
        'azione' => [
            [
                'name' => 'Epic Cinematic Trailer',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/10/audio_2b931ddbe7.mp3',
                'duration' => 173,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Epic Adventure',
                'url' => 'https://cdn.pixabay.com/audio/2022/10/15/audio_9a77e7c33e.mp3',
                'duration' => 167,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Epic Dramatic Action',
                'url' => 'https://cdn.pixabay.com/audio/2022/08/03/audio_884fe92c21.mp3',
                'duration' => 142,
                'credits' => 'Pixabay'
            ]
        ],
        'relax' => [
            [
                'name' => 'Ambient Relaxing',
                'url' => 'https://cdn.pixabay.com/audio/2022/04/27/audio_34acdfed41.mp3',
                'duration' => 180,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Calm Meditation',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/09/audio_6b7e9dbcee.mp3',
                'duration' => 129,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Gentle Acoustic',
                'url' => 'https://cdn.pixabay.com/audio/2021/11/25/audio_12f8d8c0a3.mp3',
                'duration' => 143,
                'credits' => 'Pixabay'
            ]
        ],
        'divertimento' => [
            [
                'name' => 'Happy Upbeat',
                'url' => 'https://cdn.pixabay.com/audio/2022/01/11/audio_dc98a21387.mp3',
                'duration' => 157,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Fun Quirky',
                'url' => 'https://cdn.pixabay.com/audio/2022/03/10/audio_c8602ba40d.mp3',
                'duration' => 131,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Funny Cartoon',
                'url' => 'https://cdn.pixabay.com/audio/2021/08/08/audio_dc39bbc137.mp3',
                'duration' => 114,
                'credits' => 'Pixabay'
            ]
        ],
        'vacanze' => [
            [
                'name' => 'Summer Vibes',
                'url' => 'https://cdn.pixabay.com/audio/2022/05/16/audio_d59975100b.mp3',
                'duration' => 125,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Tropical Beach',
                'url' => 'https://cdn.pixabay.com/audio/2022/05/23/audio_fc60a44f1e.mp3',
                'duration' => 167,
                'credits' => 'Pixabay'
            ],
            [
                'name' => 'Travel Adventure',
                'url' => 'https://cdn.pixabay.com/audio/2021/10/25/audio_77eaa76ec9.mp3',
                'duration' => 139,
                'credits' => 'Pixabay'
            ]
        ]
    ];
}

/**
 * Scarica un audio di sottofondo e lo salva localmente
 * 
 * @param string $url URL dell'audio da scaricare
 * @param string $outputPath Percorso dove salvare l'audio
 * @return bool Successo dell'operazione
 */
function downloadAudio($url, $outputPath) {
    // Crea la directory se non esiste
    $dir = dirname($outputPath);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Scarica il file
    $fileContent = file_get_contents($url);
    if ($fileContent === false) {
        return false;
    }
    
    // Salva il file
    $result = file_put_contents($outputPath, $fileContent);
    return $result !== false;
}

/**
 * Applica un audio di sottofondo a un video
 * 
 * @param string $videoPath Percorso del video
 * @param string $audioPath Percorso dell'audio
 * @param string $outputPath Percorso del video con audio
 * @param float $volume Volume dell'audio (0.0-1.0)
 * @return bool Successo dell'operazione
 */
function applyBackgroundAudio($videoPath, $audioPath, $outputPath, $volume = 0.3) {
    // Ottieni la durata del video
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
    $videoDuration = floatval(trim(shell_exec($cmd)));
    
    // Crea un file audio della stessa durata del video (loop se necessario)
    $tempAudioPath = dirname($outputPath) . '/temp_audio_' . uniqid() . '.mp3';
    $audioCmd = "ffmpeg -i " . escapeshellarg($audioPath) . " -filter_complex \"aloop=loop=-1:size=2000000000,atrim=0:{$videoDuration}\" " . escapeshellarg($tempAudioPath);
    exec($audioCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return false;
    }
    
    // Mixa l'audio originale del video con l'audio di sottofondo
    $mixCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
              " -i " . escapeshellarg($tempAudioPath) . 
              " -filter_complex \"[0:a]volume=1[a1];[1:a]volume={$volume}[a2];[a1][a2]amix=inputs=2:duration=first:dropout_transition=0\" " .
              " -c:v copy " . escapeshellarg($outputPath);
    exec($mixCmd, $mixOutput, $mixReturnCode);
    
    // Rimuovi il file audio temporaneo
    if (file_exists($tempAudioPath)) {
        unlink($tempAudioPath);
    }
    
    return $mixReturnCode === 0 && file_exists($outputPath);
}

/**
 * Ottiene un audio casuale da una categoria
 * 
 * @param string $category Categoria dell'audio
 * @return array|null Informazioni sull'audio selezionato
 */
function getRandomAudioFromCategory($category) {
    $catalog = getAudioCatalog();
    
    if (!isset($catalog[$category]) || empty($catalog[$category])) {
        return null;
    }
    
    $categoryAudios = $catalog[$category];
    return $categoryAudios[array_rand($categoryAudios)];
}
?>
