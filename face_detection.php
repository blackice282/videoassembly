<?php
require_once 'config.php';

/**
 * Simula l'applicazione della privacy sui volti tramite smile
 * (può essere collegato a un microservizio Flask o futuro modulo interno)
 *
 * @param string $inputPath  Percorso file video input
 * @param string $outputPath Percorso file video output
 * @return bool true se successo, false altrimenti
 */
function applyFacePrivacy($inputPath, $outputPath) {
    // Per ora simuliamo semplicemente la copia (nessuna vera modifica)
    return copy($inputPath, $outputPath);

    // Se hai un microservizio Flask:
    /*
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
        return false;
    }
    */
}
?>
