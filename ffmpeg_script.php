<?php

/**
 * Converte un MP4 in un segmento .ts (stream copy).
 */
function convertToTs(string $inputFile, string $outputTs) {
    $cmd = sprintf(
        'ffmpeg -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

/**
 * Converte un’immagine in un segmento .ts di durata fissa (3 s).
 */
function convertImageToTs(string $imagePath, string $outputTs) {
    $cmd = sprintf(
        'ffmpeg -loop 1 -i %s -t 3 -c:v libx264 -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" -pix_fmt yuv420p -f mpegts %s',
        escapeshellarg($imagePath),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

/**
 * Processa un video MP4: applica ticker, mix audio e genera thumbnail.
 */
function process_video(string $videoPath, ?string $backgroundAudio = null, ?string $tickerText = null): array {
    $processId     = uniqid();
    $tempDir       = "temp/{$processId}";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "{$tempDir}/processed_video.mp4";
    $thumbnailPath   = "{$tempDir}/thumbnail.jpg";

    $filters = [];
    if ($tickerText) {
        $safeText  = addslashes($tickerText);
        $filters[] = "drawtext=text='{$safeText}':fontcolor=white:fontsize=24"
                   . ":x=w-mod(t*100\\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
    }

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        $filters[]       = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $filter_complex  = implode(',', $filters);
        $cmd             = "ffmpeg -i " . escapeshellarg($videoPath)
                         . " -i " . escapeshellarg($backgroundAudio)
                         . " -filter_complex \"{$filter_complex}\""
                         . " -map 0:v -map \"[aud]\" -shortest -c:v libx264 -c:a aac "
                         . escapeshellarg($outputVideoPath);
    } else {
        $filter_complex = implode(',', $filters);
        $vfOption       = $filter_complex ? "-vf \"{$filter_complex}\"" : '';
        $cmd            = "ffmpeg -i " . escapeshellarg($videoPath)
                        . " {$vfOption} -c:v libx264 -c:a aac "
                        . escapeshellarg($outputVideoPath);
    }

    exec($cmd, $output, $returnCode);
    if (!file_exists($outputVideoPath)) {
        return [
            'success' => false,
            'message' => 'Errore: il file video non è stato generato.',
            'cmd'     => $cmd
        ];
    }

    // thumbnail al secondo 3
    $thumbCmd = "ffmpeg -i " . escapeshellarg($outputVideoPath)
              . " -ss 00:00:03 -vframes 1 " . escapeshellarg($thumbnailPath);
    exec($thumbCmd);

    return [
        'success'       => true,
        'video_url'     => $outputVideoPath,
        'thumbnail_url' => $thumbnailPath
    ];
}
