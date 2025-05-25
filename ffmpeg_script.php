<?php
/**
 * ffmpeg_script.php
 * Contiene tutte le funzioni FFmpeg usate da index.php.
 */

/**
 * Converte un file MP4 in segmento .ts “raw” per il concat demuxer.
 */
function convertToTs(string $inputFile, string $outputTs): void {
    // usa tutti i core CPU e preset ultrafast
    $cmd = sprintf(
        'ffmpeg -threads 0 -preset ultrafast -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

/**
 * Crea un breve segmento .ts (3s) da un’immagine statica.
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
 * Applica ticker testuale e audio di background a un MP4.
 * Restituisce ['success'=>bool,'video_url'=>string, …].
 */
function process_video(string $videoPath, ?string $backgroundAudio = null, ?string $tickerText = null): array {
    $id      = uniqid();
    $tempDir = "temp/$id";
    if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);
    $out     = "$tempDir/processed_video.mp4";
    $filters = [];

    if ($tickerText) {
        $t = addslashes($tickerText);
        $filters[] = "drawtext=text='$t':fontcolor=white:fontsize=24:x=w-mod(t*100\\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5";
    }

    if ($backgroundAudio && file_exists($backgroundAudio)) {
        // mix video + audio
        $filters[] = "[1:a]volume=0.6,afade=t=in:st=0:d=2,afade=t=out:st=999:d=2[aud]";
        $fc = implode(",", $filters);
        $cmd = sprintf(
            'ffmpeg -i %s -i %s -filter_complex "%s" -map 0:v -map "[aud]" -shortest -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            escapeshellarg($backgroundAudio),
            $fc,
            escapeshellarg($out)
        );
    } else {
        $vf = $filters ? sprintf('-vf "%s"', implode(",", $filters)) : '';
        $cmd = sprintf(
            'ffmpeg -i %s %s -c:v libx264 -c:a aac %s',
            escapeshellarg($videoPath),
            $vf,
            escapeshellarg($out)
        );
    }

    exec($cmd . ' 2>&1', $log, $rc);
    if ($rc !== 0 || !file_exists($out)) {
        return ['success'=>false,'message'=>'Process failed','cmd'=>$cmd];
    }
    return ['success'=>true,'video_url'=>$out];
}

/**
 * Concatena in un unico MP4 un array di segmenti .ts.
 * Se serve applica anche audio di background + ticker.
 */
function concatenateTsFiles(array $tsFiles, string $outputFile, ?string $audioPath = null, ?string $tickerText = null): void {
    // crea lista in linea
    $list = implode('|', $tsFiles);
    $tmp  = "temp/merged_" . uniqid() . ".mp4";
    // concat demuxer “inline”
    shell_exec("ffmpeg -i \"concat:$list\" -c copy -bsf:a aac_adtstoasc \"$tmp\"");
    if ($audioPath || $tickerText) {
        // rinomina il file finale
        $res = process_video($tmp, $audioPath, $tickerText);
        if ($res['success']) {
            copy($res['video_url'], $outputFile);
        } else {
            copy($tmp, $outputFile);
        }
    } else {
        copy($tmp, $outputFile);
    }
    @unlink($tmp);
}

/**
 * Cancella file temporanei (.ts, segmenti, ecc).
 */
function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
    foreach ($files as $f) {
        if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/')===false)) {
            @unlink($f);
        }
    }
}
