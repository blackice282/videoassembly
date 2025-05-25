<?php
/**
 * ffmpeg_script.php
 * Contiene tutte le funzioni FFmpeg: video, ticker, convertToTs, convertImageToTs, process_video, ecc.
 */

function convertToTs(string $inputFile, string $outputTs): void {
    // usa tutti i core CPU e preset ultrafast (per NVENC: sostituisci libx264 con h264_nvenc)
    $cmd = sprintf(
        'ffmpeg -threads 0 -preset ultrafast -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function convertImageToTs(string $imagePath, string $outputTs, int $duration = 3): void {
    // loop immagine, durata fissa, threading e preset ultrafast
    $cmd = sprintf(
        'ffmpeg -threads 0 -preset ultrafast -loop 1 -i %s -c:v libx264 -t %d -pix_fmt yuv420p -f mpegts %s',
        escapeshellarg($imagePath),
        $duration,
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function process_video(string $videoPath, ?string $backgroundAudio = null, ?string $tickerText = null): array {
    $processId       = uniqid();
    $tempDir         = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "$tempDir/processed_video.mp4";
    $filters         = [];

    if ($tickerText) {
        $safeText     = addslashes($tickerText);
        $filters[]    = "drawtext=text='$safeText':fontcolor=white:fontsize=24:x=w-mod(t*100\\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
    }

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        $filters[]      = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $filter_complex = implode(",", $filters);
        $cmd = sprintf(
            'ffmpeg -threads 0 -preset ultrafast -i %s -i %s -filter_complex "%s" -map 0:v -map "[aud]" -shortest -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            escapeshellarg($backgroundAudio),
            $filter_complex,
            escapeshellarg($outputVideoPath)
        );
    } else {
        $filter_complex = implode(",", $filters);
        $vfOption       = $filter_complex ? sprintf('-vf "%s"', $filter_complex) : '';
        $cmd = sprintf(
            'ffmpeg -threads 0 -preset ultrafast -i %s %s -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            $vfOption,
            escapeshellarg($outputVideoPath)
        );
    }

    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0 || !file_exists($outputVideoPath)) {
        return [
            'success' => false,
            'message' => 'Process failed',
            'cmd'     => $cmd
        ];
    }

    return [
        'success'   => true,
        'video_url' => $outputVideoPath
    ];
}
