<?php
// duration_editor.php - Funzione per troncare video con FFmpeg senza ricodifica
function trimVideo($inputPath, $outputPath, $durationSec) {
    // -y sovrascrive senza chiedere
    $cmd = sprintf(
        'ffmpeg -y -i %s -t %d -c copy %s 2>&1',
        escapeshellarg($inputPath),
        intval($durationSec),
        escapeshellarg($outputPath)
    );
    exec($cmd, $output, $returnVar);
    return $returnVar === 0;
}
?>