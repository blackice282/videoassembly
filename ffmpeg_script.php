<?php
function process_video($videoPath, $backgroundAudio = null) {
    $processId = uniqid();
    $tempDir = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "$tempDir/processed_video.mp4";
    $thumbnailPath = "$tempDir/thumbnail.jpg";

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) .
               " -i " . escapeshellarg($backgroundAudio) .
               " -map 0:v:0 -map 1:a:0 -shortest -c:v libx264 -c:a aac -strict experimental " .
               escapeshellarg($outputVideoPath);
    } else {
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) .
               " -c:v libx264 -c:a aac -strict experimental " .
               escapeshellarg($outputVideoPath);
    }

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        return [
            'success' => false,
            'message' => 'Errore nell\'elaborazione del video: ' . implode("\n", $output)
        ];
    }

    $thumbnailCmd = "ffmpeg -i " . escapeshellarg($outputVideoPath) .
                    " -ss 00:00:03 -vframes 1 " . escapeshellarg($thumbnailPath);
    exec($thumbnailCmd);

    return [
        'success' => true,
        'video_url' => "https://your-app-name.onrender.com/$outputVideoPath",
        'thumbnail_url' => "https://your-app-name.onrender.com/$thumbnailPath"
    ];
}
?>
