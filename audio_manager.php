<?php
// Aggiungi questa funzione in audio_manager.php dopo le funzioni esistenti

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
        return true;
    }
    
    // Tenta di scaricare il file usando file_get_contents
    try {
        $fileContent = file_get_contents($url);
        if ($fileContent === false) {
            // Se fallisce, prova con cURL
            return downloadAudioWithCurl($url, $outputPath);
        }
        
        $result = file_put_contents($outputPath, $fileContent);
        return ($result !== false);
    } catch (Exception $e) {
        error_log("Errore nel download dell'audio con file_get_contents: " . $e->getMessage());
        // Fallback a cURL
        return downloadAudioWithCurl($url, $outputPath);
    }
}

/**
 * Scarica un file audio usando cURL (fallback)
 * 
 * @param string $url URL dell'audio da scaricare
 * @param string $outputPath Percorso di destinazione
 * @return bool Successo dell'operazione
 */
function downloadAudioWithCurl($url, $outputPath) {
    // Verifica se cURL è disponibile
    if (!function_exists('curl_init')) {
        error_log("cURL non è disponibile sul sistema");
        return false;
    }
    
    $ch = curl_init($url);
    $fp = fopen($outputPath, 'wb');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $success = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("Errore cURL: " . curl_error($ch));
        $success = false;
    }
    
    curl_close($ch);
    fclose($fp);
    
    // Verifica che il file sia stato scaricato correttamente
    if (!$success || !file_exists($outputPath) || filesize($outputPath) <= 0) {
        if (file_exists($outputPath)) {
            unlink($outputPath); // Rimuovi il file incompleto
        }
        return false;
    }
    
    return true;
}
?>
