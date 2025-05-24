<?php
/**
 * ffmpeg_script.php
 * Contiene tutte le funzioni FFmpeg: video, ticker, convertToTs, convertImageToTs, process_video, ecc.
 */

function convertToTs(string $inputFile, string $outputTs): void {
    $cmd = sprintf(
        'ffmpeg -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function convertImageToTs(string $imagePath, string $outputTs, int $duration = 3): void {
    // Crea un breve video TS dalla singola immagine (loop 1, durata fissa)
    $cmd = sprintf(
        'ffmpeg -loop 1 -i %s -c:v libx264 -t %d -pix_fmt yuv420p -vf "scale=1280:720,format=yuv420p" -f mpegts %s',
        escapeshellarg($imagePath),
        $duration,
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function process_video(string $videoPath, ?string $backgroundAudio = null, ?string $tickerText = null): array {
    $processId    = uniqid();
    $tempDir      = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "$tempDir/processed_video.mp4";
    $filters         = [];

    // 1) Ticker testuale
    if ($tickerText) {
        $safeText = addslashes($tickerText);
        $filters[] = "drawtext=text='$safeText':fontcolor=white:fontsize=24:x=w-mod(t*100\\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
    }

    // 2) Costruisco il comando con o senza audio di background
    if ($backgroundAudio && file_exists($backgroundAudio)) {
        $filters[] = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $filter_complex = implode(",", $filters);
        $cmd = sprintf(
            'ffmpeg -i %s -i %s -filter_complex "%s" -map 0:v -map "[aud]" -shortest -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            escapeshellarg($backgroundAudio),
            $filter_complex,
            escapeshellarg($outputVideoPath)
        );
    } else {
        $filter_complex = implode(",", $filters);
        $vfOption       = $filter_complex ? sprintf('-vf "%s"', $filter_complex) : '';
        $cmd = sprintf(
            'ffmpeg -i %s %s -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            $vfOption,
            escapeshellarg($outputVideoPath)
        );
    }

    // 3) Eseguo il comando
    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0 || !file_exists($outputVideoPath)) {
        return [
            'success' => false,
            'message' => 'Process failed',
            'cmd'     => $cmd
        ];
    }

    return [
        'success'    => true,
        'video_url'  => $outputVideoPath
    ];
}
?>
