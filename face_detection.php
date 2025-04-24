<?php
require_once 'config.php';

/**
 * Invia il video al microservizio Flask e scarica il risultato
 *
 * @param string $inputPath  Percorso locale del file video da elaborare
 * @param string $outputPath Percorso dove salvare il file risultante
 * @return bool true se successo, false se errore
 */
function applyFacePrivacy($inputPath, $outputPath) {
    $ch = curl_init();
    $cfile = new CURLFile($inputPath, 'video/mp4', basename($inputPath));

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://TUO_MICROSERVIZIO.onrender.com/process',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['video' => $cfile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        file_put_contents($outputPath, $response);
        return file_exists($outputPath) && filesize($outputPath) > 0;
    } else {
        error_log("Errore dal microservizio (HTTP $httpCode)");
        return false;
    }
}
