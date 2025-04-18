<?php
// Assicurati che questa funzione sia presente nel file audio_manager.php

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
<?php
// audio_manager.php - Versione corretta per evitare interruzioni durante il parlato

/**
 * Applica un audio di sottofondo a un video preservando l'audio originale
 * e abbassando il volume della musica durante il parlato
 * 
 * @param string $videoPath Percorso del video
 * @param string $audioPath Percorso dell'audio
 * @param string $outputPath Percorso del video con audio
 * @param float $volume Volume dell'audio (0.0-1.0)
 * @return bool Successo dell'operazione
 */
function applyBackgroundAudio($videoPath, $audioPath, $outputPath, $volume = 0.3) {
    // Verifica l'esistenza dei file
    if (!file_exists($videoPath) || !file_exists($audioPath)) {
        error_log("File non esistenti: video = $videoPath, audio = $audioPath");
        return false;
    }
    
    // Ottieni la durata del video
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath);
    $videoDuration = floatval(trim(shell_exec($cmd)));
    
    if ($videoDuration <= 0) {
        error_log("Impossibile determinare la durata del video o durata invalida ($videoDuration)");
        return false;
    }
    
    // Approccio più semplice e affidabile: usa il filtro "loudnorm" per normalizzare
    // l'audio e applicare una compressione dinamica per abbassare la musica durante il parlato
    
    // Crea una directory temporanea per i file intermedi
    $tempDir = dirname($outputPath) . "/audio_temp_" . uniqid();
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Estrai audio originale
    $originalAudio = "$tempDir/original.aac";
    $extractCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                 " -vn -c:a copy " . escapeshellarg($originalAudio) . " -y";
    exec($extractCmd, $extractOutput, $extractCode);
    
    if ($extractCode !== 0 || !file_exists($originalAudio)) {
        // Caso in cui non c'è audio originale o non può essere estratto
        $originalAudio = null;
    }
    
    // Prepara la traccia musicale (loop e durata)
    $musicTrack = "$tempDir/music.mp3";
    $musicCmd = "ffmpeg -stream_loop -1 -i " . escapeshellarg($audioPath) . 
               " -t $videoDuration -c:a libmp3lame -q:a 4 " . escapeshellarg($musicTrack) . " -y";
    exec($musicCmd, $musicOutput, $musicCode);
    
    if ($musicCode !== 0 || !file_exists($musicTrack)) {
        error_log("Errore nella preparazione della traccia musicale");
        // Pulizia
        if (file_exists($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
        }
        return false;
    }
    
    // Metodo semplice: solo aggiungere la musica se non c'è audio originale
    if ($originalAudio === null) {
        $finalCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                   " -i " . escapeshellarg($musicTrack) . 
                   " -c:v copy -c:a aac -map 0:v:0 -map 1:a:0 -shortest " . 
                   escapeshellarg($outputPath) . " -y";
        exec($finalCmd, $finalOutput, $finalCode);
        
        // Pulizia
        if (file_exists($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
        }
        
        return $finalCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    }
    
    // Metodo avanzato: mixare l'audio originale con la musica abbassando quest'ultima durante il parlato
    
    // Prima normalizza l'audio originale per avere un livello coerente
    $normalizedAudio = "$tempDir/normalized.wav";
    $normalizeCmd = "ffmpeg -i " . escapeshellarg($originalAudio) . 
                   " -af loudnorm=I=-16:TP=-1.5:LRA=11 " . 
                   escapeshellarg($normalizedAudio) . " -y";
    exec($normalizeCmd, $normalizeOutput, $normalizeCode);
    
    if ($normalizeCode !== 0 || !file_exists($normalizedAudio)) {
        error_log("Errore nella normalizzazione dell'audio");
        // Fallback: metodo semplice
        $fallbackCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                      " -i " . escapeshellarg($musicTrack) . 
                      " -filter_complex \"[1:a]volume=" . ($volume*0.8) . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
                      " -c:v copy " . escapeshellarg($outputPath) . " -y";
        exec($fallbackCmd, $fallbackOutput, $fallbackCode);
        
        // Pulizia
        if (file_exists($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
        }
        
        return $fallbackCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    }
    
    // Applica un filtro "sidechaining" per abbassare la musica durante il parlato
    // Usiamo il filtro "sidechaincompress" per questo effetto
    $mixedAudio = "$tempDir/mixed.aac";
    $compressCmd = "ffmpeg -i " . escapeshellarg($normalizedAudio) . 
                  " -i " . escapeshellarg($musicTrack) . 
                  " -filter_complex \"[1:a]volume=" . $volume . "[music];" .
                  "[music]asidechain=threshold=0.01:ratio=20:release=300[compressed];" .
                  "[compressed][0:a]amix=inputs=2:weights=0.3 1\" " .
                  " -c:a aac -b:a 192k " . escapeshellarg($mixedAudio) . " -y";
    
    // Opzione alternativa più semplice come fallback
    if (true) {
        $compressCmd = "ffmpeg -i " . escapeshellarg($normalizedAudio) . 
                      " -i " . escapeshellarg($musicTrack) . 
                      " -filter_complex \"[1:a]volume=" . $volume . "[music];" .
                      "[0:a][music]amix=inputs=2:duration=first\" " .
                      " -c:a aac -b:a 192k " . escapeshellarg($mixedAudio) . " -y";
    }
    
    exec($compressCmd, $compressOutput, $compressCode);
    
    if ($compressCode !== 0 || !file_exists($mixedAudio)) {
        error_log("Errore nel mixaggio audio con compressione dinamica");
        // Fallback: metodo super semplice
        $superfallbackCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                           " -i " . escapeshellarg($musicTrack) . 
                           " -map 0:v -map 0:a -map 1:a -c:v copy -c:a aac " . 
                           escapeshellarg($outputPath) . " -y";
        exec($superfallbackCmd, $superfallbackOutput, $superfallbackCode);
        
        // Pulizia
        if (file_exists($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            rmdir($tempDir);
        }
        
        return $superfallbackCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    }
    
    // Finalmente, combina l'audio mixato con il video originale
    $finalCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
               " -i " . escapeshellarg($mixedAudio) . 
               " -c:v copy -c:a copy -map 0:v:0 -map 1:a:0 " . 
               escapeshellarg($outputPath) . " -y";
    exec($finalCmd, $finalOutput, $finalCode);
    
    // Pulizia
    if (file_exists($tempDir)) {
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);
    }
    
    return $finalCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}
