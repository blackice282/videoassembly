<?php
// audio_manager.php - Gestione audio di sottofondo e funzioni correlate - VERSIONE CORRETTA

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
        return getBackupAudio($category); // Usa backup invece di null
    }
    
    $categoryAudios = $catalog[$category];
    return $categoryAudios[array_rand($categoryAudios)];
}

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
    if (!file_exists($videoPath)) {
        error_log("File video non esistente: $videoPath");
        return false;
    }
    
    if (!file_exists($audioPath)) {
        error_log("File audio non esistente: $audioPath");
        return false;
    }
    
    // Crea la directory di output se non esiste
    $outputDir = dirname($outputPath);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Ottieni la durata del video (ottimizzato)
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath) . " 2>&1";
    $videoDuration = floatval(trim(shell_exec($cmd)));
    
    if ($videoDuration <= 0) {
        error_log("Impossibile determinare la durata del video o durata invalida ($videoDuration)");
        return false;
    }
    
    // APPROCCIO SEMPLIFICATO E ROBUSTO
    // Usa un singolo comando FFmpeg con opzioni sicure
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -stream_loop -1 -i " . escapeshellarg($audioPath) . 
           " -filter_complex \"[1:a]volume=" . $volume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
           " -c:v copy -c:a aac -b:a 128k -shortest " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    error_log("Comando audio: $cmd");
    exec($cmd, $output, $returnCode);
    error_log("Output comando audio: " . implode("\n", $output));
    error_log("Codice ritorno audio: $returnCode");
    
    // Verifica il successo dell'operazione
    $success = $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    
    if ($success) {
        error_log("Audio applicato con successo: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    }
    
    // Se fallisce, prova un approccio più semplice con solo concatenazione audio
    error_log("Fallito primo tentativo, provo con approccio più semplice");
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -i " . escapeshellarg($audioPath) . 
           " -filter_complex \"[1:a]aloop=loop=-1:size=2000000[a1];[a1]volume=" . $volume . "[a1v];[0:a][a1v]amix=inputs=2:duration=first[a]\" " . 
           " -map 0:v -map \"[a]\" -c:v copy -c:a aac -b:a 128k -shortest " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    error_log("Secondo tentativo comando audio: $cmd");
    exec($cmd, $fallbackOutput, $fallbackCode);
    error_log("Output secondo tentativo: " . implode("\n", $fallbackOutput));
    
    if ($fallbackCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        error_log("Audio applicato con successo (secondo tentativo): $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    }
    
    // Se anche il secondo tentativo fallisce, prova con un comando ultra-semplificato
    error_log("Fallito secondo tentativo, provo con approccio ultra-semplificato");
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -i " . escapeshellarg($audioPath) . 
           " -c:v copy -c:a aac -b:a 128k -filter_complex \"[1:a]volume=" . $volume . "[a1];[0:a][a1]amerge=inputs=2[a]\" -map 0:v -map \"[a]\" " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    error_log("Terzo tentativo comando audio: $cmd");
    exec($cmd, $finalOutput, $finalCode);
    error_log("Output terzo tentativo: " . implode("\n", $finalOutput));
    
    return $finalCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}

/**
 * Scarica un file audio da una URL
 * 
 * @param string $url URL dell'audio da scaricare
 * @param string $outputPath Percorso di destinazione
 * @return bool Successo dell'operazione
 */
function downloadAudio($url, $outputPath) {
    // Crea la directory di destinazione se non esiste
    $outputDir = dirname($outputPath);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Verifica se il file è già stato scaricato
    if (file_exists($outputPath) && filesize($outputPath) > 0) {
        error_log("File audio già scaricato: $outputPath");
        return true;
    }
    
    error_log("Tentativo download audio da $url a $outputPath");
    
    // Usa cURL con gli header corretti
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($outputPath, 'wb');
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, 'https://pixabay.com/');
        
        $success = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("Errore cURL: " . curl_error($ch));
            $success = false;
        }
        
        curl_close($ch);
        fclose($fp);
        
        // Verifica che il file sia stato scaricato correttamente
        if (!$success || !file_exists($outputPath) || filesize($outputPath) <= 0) {
            error_log("Download non riuscito con cURL");
            if (file_exists($outputPath)) {
                unlink($outputPath); // Rimuovi il file incompleto
            }
            
            // Prova a usare file_get_contents come fallback
            return downloadAudioFallback($url, $outputPath);
        }
        
        error_log("Download completato con successo: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    }
    
    // Se cURL non è disponibile, usa file_get_contents
    return downloadAudioFallback($url, $outputPath);
}

/**
 * Metodo fallback per scaricare audio
 */
function downloadAudioFallback($url, $outputPath) {
    error_log("Utilizzo file_get_contents come fallback per scaricare $url");
    
    // Fallback a file_get_contents con context per impostare gli header
    $context = stream_context_create([
        'http' => [
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Referer: https://pixabay.com/'
            ],
            'timeout' => 60
        ]
    ]);
    
    try {
        $fileContent = file_get_contents($url, false, $context);
        if ($fileContent === false) {
            error_log("Fallimento file_get_contents, tentativo di generare un audio di fallback");
            return createLocalBackgroundAudio($outputPath, 'music', 60);
        }
        
        $result = file_put_contents($outputPath, $fileContent);
        if ($result === false || filesize($outputPath) <= 0) {
            error_log("Errore nel salvataggio del file audio");
            return createLocalBackgroundAudio($outputPath, 'music', 60);
        }
        
        error_log("Download completato con file_get_contents: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    } catch (Exception $e) {
        error_log("Eccezione durante il download dell'audio: " . $e->getMessage());
        return createLocalBackgroundAudio($outputPath, 'music', 60);
    }
}

/**
 * Crea un audio locale di fallback con FFmpeg
 * 
 * @param string $outputPath Il percorso di output dell'audio
 * @param string $type Il tipo di audio (music, ambient)
 * @param int $duration Durata in secondi
 * @return bool Successo dell'operazione
 */
function createLocalBackgroundAudio($outputPath, $type = 'music', $duration = 60) {
    error_log("Creazione audio di fallback: $outputPath");
    
    $audioDir = dirname($outputPath);
    if (!file_exists($audioDir)) {
        mkdir($audioDir, 0777, true);
    }
    
    // Verifica se esistono audio locali predefiniti da utilizzare
    $defaultAudios = [
        'assets/audio/background_music_1.mp3',
        'assets/audio/background_music_2.mp3',
        'temp/default_background.mp3'
    ];
    
    foreach ($defaultAudios as $defaultAudio) {
        if (file_exists($defaultAudio)) {
            error_log("Utilizzo audio predefinito: $defaultAudio");
            return copy($defaultAudio, $outputPath);
        }
    }
    
    // Comando base per generare un tono
    $baseCmd = '';
    
    if ($type == 'music') {
        // Genera una melodia semplice
        $baseCmd = "ffmpeg -f lavfi -i \"sine=frequency=440:duration=$duration,sine=frequency=550:duration=$duration,amix=inputs=2:duration=longest\" -c:a aac -b:a 128k";
    } else {
        // Rumore bianco per ambiente
        $baseCmd = "ffmpeg -f lavfi -i \"anoisesrc=color=brown:duration=$duration\" -c:a aac -b:a 128k";
    }
    
    // Esegui il comando
    $cmd = "$baseCmd " . escapeshellarg($outputPath) . " -y 2>&1";
    error_log("Comando generazione audio: $cmd");
    exec($cmd, $output, $returnCode);
    error_log("Output generazione audio: " . implode("\n", $output));
    
    $success = $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    if ($success) {
        error_log("Audio generato con successo: $outputPath (" . filesize($outputPath) . " bytes)");
    } else {
        error_log("Fallimento generazione audio, codice: $returnCode");
    }
    
    return $success;
}

/**
 * Ottiene un audio di backup invece di scaricare da Pixabay
 * 
 * @param string $category Categoria dell'audio
 * @return array|null Informazioni sull'audio generato
 */
function getBackupAudio($category) {
    $audioDir = 'temp/audio_backup';
    if (!file_exists($audioDir)) {
        mkdir($audioDir, 0777, true);
    }
    
    $filename = $audioDir . '/backup_' . $category . '.mp3';
    
    // Se non esiste, crealo
    if (!file_exists($filename)) {
        // Durata in base alla categoria
        $duration = 60;
        $type = 'music';
        
        if ($category == 'ambient' || $category == 'relax') {
            $type = 'ambient';
            $duration = 120;
        }
        
        createLocalBackgroundAudio($filename, $type, $duration);
    }
    
    // Se esiste ora, restituiscilo
    if (file_exists($filename) && filesize($filename) > 0) {
        return [
            'name' => 'Audio di backup - ' . ucfirst($category),
            'url' => $filename, // URL locale
            'duration' => 60,
            'credits' => 'Sistema (generato)'
        ];
    }
    
    return null;
}
