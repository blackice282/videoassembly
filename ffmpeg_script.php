<?php
/**
 * ffmpeg_script.php
 * Contiene tutte le funzioni per interagire con FFmpeg:
 * - convertToTs(): trasforma un file video in segmento .ts
 * - convertImageToTs(): crea un segmento .ts di durata fissa da un’immagine
 * - process_video(): applica ticker e audio di background
 * - concatenateTsFiles(): concatena insieme segmenti .ts e poi applica process_video
 */

/**
 * Converte un video in un segmento MPEG-TS (usando tutti i core e preset ultrafast).
 */
function convertToTs(string $inputFile, string $outputTs): void {
    $cmd = sprintf(
        'ffmpeg -threads 0 -preset ultrafast -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

/**
 * Da un’immagine crea un breve segmento .ts di durata fissa (default 3s).
 */
function convertImageToTs(string $imagePath, string $outputTs, int $duration = 3): void {
    $cmd = sprintf(
        'ffmpeg -threads 0 -preset ultrafast -loop 1 -i %s -c:v libx264 -t %d -pix_fmt yuv420p -f mpegts %s',
        escapeshellarg($imagePath),
        $duration,
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

/**
 * Applica ticker testuale e/o audio di background a un video già concatenato.
 * Ritorna un array con successo, percorso video, eventuale messaggio di errore.
 */
function process_video(string $videoPath, ?string $backgroundAudio = null, ?string $tickerText = null): array {
    $processId = uniqid();
    $tempDir = "temp/$processId";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

    $outputVideoPath = "$tempDir/processed_video.mp4";
    $filters = [];

    // ticker
    if ($tickerText) {
        $safe = addslashes($tickerText);
        $filters[] = "drawtext=text='$safe':fontcolor=white:fontsize=24:x=w-mod(t*100\\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
    }

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        // audio + ticker insieme
        $filters[] = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $filterComplex = implode(",", $filters);
        $cmd = sprintf(
            'ffmpeg -i %s -i %s -filter_complex "%s" -map 0:v -map "[aud]" -shortest -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            escapeshellarg($backgroundAudio),
            $filterComplex,
            escapeshellarg($outputVideoPath)
        );
    } else {
        // solo ticker se presente
        $vf = $filters ? '-vf "'.implode(",",$filters).'"' : '';
        $cmd = sprintf(
            'ffmpeg -i %s %s -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            $vf,
            escapeshellarg($outputVideoPath)
        );
    }

    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0 || !file_exists($outputVideoPath)) {
        return [
            'success' => false,
            'message' => "FFmpeg error:\n".implode("\n",$out),
            'cmd'     => $cmd
        ];
    }

    return ['success' => true, 'video_url' => $outputVideoPath];
}

/**
 * Concatena un array di segmenti .ts in un MP4 finale.
 * Se viene passato $audioPath o $tickerText, dopo la concat gli applica process_video().
 */
function concatenateTsFiles(array $tsFiles, string $outputFile, ?string $audioPath = null, ?string $tickerText = null): void {
    // 1) Prepara file di lista per il demuxer concat
    $listFile = tempnam(sys_get_temp_dir(),'concat_') . '.txt';
    $fp = fopen($listFile,'w');
    foreach ($tsFiles as $ts) {
        fwrite($fp, "file '".str_replace("'","'\\''",$ts)."'\n");
    }
    fclose($fp);

    // 2) Concat segmento TS in MP4 temporaneo
    $tempMp4 = dirname($outputFile).'/temp_'.basename($outputFile);
    $cmd = sprintf(
        'ffmpeg -f concat -safe 0 -i %s -c copy -bsf:a aac_adtstoasc %s',
        escapeshellarg($listFile),
        escapeshellarg($tempMp4)
    );
    shell_exec($cmd);
    @unlink($listFile);

    // 3) Se richiesto, applica audio/ticker
    if ($audioPath || $tickerText) {
        $res = process_video($tempMp4, $audioPath, $tickerText);
        if ($res['success']) {
            rename($res['video_url'], $outputFile);
        } else {
            rename($tempMp4, $outputFile);
        }
    } else {
        rename($tempMp4, $outputFile);
    }
}
