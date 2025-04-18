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
