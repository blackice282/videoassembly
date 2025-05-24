<?php
function process_video($videoPath, $backgroundAudio = null, $tickerText = null) {
    $processId = uniqid();
    $tempDir = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "$tempDir/processed_video.mp4";
    $thumbnailPath = "$tempDir/thumbnail.jpg";

    $filters = [];

    if ($tickerText) {
        $safeText = addslashes($tickerText);
        $drawtext = "drawtext=text='$safeText':fontcolor=white:fontsize=24:x=w-mod(t*100\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
        $filters[] = $drawtext;
    }

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        $filters[] = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $filter_complex = implode(",", $filters);
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) .
               " -i " . escapeshellarg($backgroundAudio) .
               " -filter_complex \"" . $filter_complex . "\" " .
               "-map 0:v -map \"[aud]\" -shortest -c:v libx264 -c:a aac -strict experimental " .
               escapeshellarg($outputVideoPath);
    } else {
        $filter_complex = implode(",", $filters);
        $filterOption = $filter_complex ? "-vf \"$filter_complex\"" : "";
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) .
               " $filterOption -c:v libx264 -c:a aac -strict experimental " .
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
