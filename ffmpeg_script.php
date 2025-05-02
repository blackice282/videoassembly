<?php
// ffmpeg_script.php
require_once __DIR__ . '/config.php';

/**
 * Concatena più clip in un unico MP4 tramite segmenti MPEG‑TS,
 * molto più affidabile del concat demuxer sui MP4 diretti.
 *
 * @param array  $inputs Array di percorsi MP4
 * @param string $out    Percorso file MP4 di output
 * @return string        Percorso dell’output
 */
function applyTransitions(array $inputs, string $out): string {
    // 1) Directory temporanea per i segmenti .ts
    $tempDir = getConfig('paths.temp') . '/ts_' . uniqid();
    if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

    $tsFiles = [];
    // 2) Converti ogni MP4 in TS
    foreach ($inputs as $i => $mp4) {
        $ts = "{$tempDir}/seg{$i}.ts";
        $cmd = FFMPEG_PATH
             . ' -y -i ' . escapeshellarg($mp4)
             . ' -c copy -bsf:v h264_mp4toannexb -f mpegts '
             . escapeshellarg($ts);
        shell_exec($cmd);
        if (file_exists($ts)) {
            $tsFiles[] = $ts;
        }
    }

    // 3) Se abbiamo segmenti, concatena in un MP4 finale
    if (count($tsFiles) > 0) {
        $concat = 'concat:' . implode('|', $tsFiles);
        $cmd2 = FFMPEG_PATH
              . ' -y -i ' . escapeshellarg($concat)
              . ' -c copy -bsf:a aac_adtstoasc '
              . escapeshellarg($out);
        shell_exec($cmd2);
    }

    // 4) Pulisci i .ts
    foreach ($tsFiles as $ts) {
        @unlink($ts);
    }
    @rmdir($tempDir);

    return $out;
}
