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
    $fileContent = @file_get_contents($url);
    if ($fileContent === false) {
        error_log("Impossibile scaricare l'audio da: $url");
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
    
    if ($videoDuration <= 0) {
        error_log("Impossibile determinare la durata del video");
        return false;
    }
    
    // Verifica che l'audio esista
    if (!file_exists($audioPath)) {
        error_log("File audio non trovato: $audioPath");
        return false;
    }
    
    // Metodo semplificato per aggiungere l'audio
    // Prima estrae l'audio originale
    $originalAudioPath = dirname($outputPath) . '/original_audio_' . uniqid() . '.aac';
    $extractCmd = "ffmpeg -i " . escapeshellarg($videoPath) . " -vn -c:a copy " . escapeshellarg($originalAudioPath);
    exec($extractCmd);
    
    // Poi crea un file audio in loop della lunghezza del video
    $loopedAudioPath = dirname($outputPath) . '/looped_audio_' . uniqid() . '.mp3';
    $loopCmd = "ffmpeg -stream_loop -1 -i " . escapeshellarg($audioPath) . 
              " -t $videoDuration -c:a copy " . escapeshellarg($loopedAudioPath);
    exec($loopCmd);
    
    // Infine, combina il video originale con i due audio mixati
    $mixCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
              " -i " . escapeshellarg($loopedAudioPath) . 
              " -i " . escapeshellarg($originalAudioPath) . 
              " -filter_complex \"[1:a]volume=$volume[background];[2:a][background]amix=inputs=2:duration=first\" " .
              " -c:v copy " . escapeshellarg($outputPath);
    exec($mixCmd, $mixOutput, $mixReturnCode);
    
    // Pulisci i file temporanei
    if (file_exists($originalAudioPath)) unlink($originalAudioPath);
    if (file_exists($loopedAudioPath)) unlink($loopedAudioPath);
    
    // Verifica se il file di output esiste e ha dimensioni
    if ($mixReturnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        return true;
    }
    
    // Fallback: se il mix fallisce, prova un metodo più semplice
    $simpleMixCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                   " -i " . escapeshellarg($audioPath) . 
                   " -c:v copy -c:a aac -map 0:v:0 -map 1:a:0 -shortest " . 
                   escapeshellarg($outputPath);
    exec($simpleMixCmd, $simpleOutput, $simpleReturnCode);
    
    return $simpleReturnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
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
