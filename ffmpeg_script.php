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
               " -filter_complex " .
               "\"[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud];" .
               "[0:v]copy[v];[v][aud]concat=n=1:v=1:a=1[outv][outa]\" " .
               " -map \"[outv]\" -map \"[outa]\" -shortest -c:v libx264 -c:a aac -strict experimental " .
               escapeshellarg($outputVideoPath);
    } else {
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) .
               " -c:v libx264 -c:a aac -strict experimental " .
               escapeshellarg($outputVideoPath);
    }

    exec($cmd, $output, $returnCode);

    if (!file_exists($outputVideoPath)) {
        return [
            'success' => false,
            'message' => 'Errore: il file video non è stato generato.',
            'cmd' => $cmd
        ];
    }

    $thumbnailCmd = "ffmpeg -i " . escapeshellarg($outputVideoPath) .
                    " -ss 00:00:03 -vframes 1 " . escapeshellarg($thumbnailPath);
    exec($thumbnailCmd);

    return [
        'success' => true,
        'video_url' => $outputVideoPath,
        'thumbnail_url' => $thumbnailPath
    ];
}
?>
