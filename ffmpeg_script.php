<?php
// ffmpeg_script.php

function process_video($videoPath) {
    // Crea una directory temporanea
    $processId = uniqid();
    $tempDir = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir);

    // Percorsi per i file elaborati
    $outputVideoPath = "$tempDir/processed_video.mp4";
    $thumbnailPath = "$tempDir/thumbnail.jpg";

    // Comando per generare il video elaborato
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . " -c:v libx264 -c:a aac -strict experimental " . escapeshellarg($outputVideoPath);
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        return [
            'success' => false,
            'message' => 'Errore nell\'elaborazione del video: ' . implode("\n", $output)
        ];
    }

    // Genera una miniatura del video
    $thumbnailCmd = "ffmpeg -i " . escapeshellarg($outputVideoPath) . " -ss 00:00:03 -vframes 1 " . escapeshellarg($thumbnailPath);
    exec($thumbnailCmd, $thumbnailOutput, $thumbnailReturnCode);

    if ($thumbnailReturnCode !== 0) {
        return [
            'success' => false,
            'message' => 'Errore nel generare la miniatura'
        ];
    }

    // Path per i file elaborati (per il download)
    $downloadVideoUrl = "https://your-app-name.onrender.com/$tempDir/processed_video.mp4";
    $thumbnailUrl = "https://your-app-name.onrender.com/$tempDir/thumbnail.jpg";

    return [
        'success' => true,
        'video_url' => $downloadVideoUrl,
        'thumbnail_url' => $thumbnailUrl
    ];
}
?>
