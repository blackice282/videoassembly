<?php
require_once __DIR__ . '/config.php';

/**
 * Taglia o accelera un video a durata target.
 *
 * @param string $in      File di input
 * @param int    $target  Durata target in secondi
 * @param string $method  'trim' o 'speed'
 * @return string         Percorso del file finale
 */
function applyDurationEdit(string $in, int $target, string $method = 'trim'): string {
    $out = getConfig('paths.temp') . '/dur_' . uniqid() . '.mp4';
    if ($method === 'trim') {
        $cmd = escapeshellcmd(FFMPEG_PATH)
             . " -y -threads 0 -preset ultrafast -i "
             . escapeshellarg($in)
             . " -t {$target} -c copy "
             . escapeshellarg($out);
    } else {
        // atempo support 0.5–2.0, calcola factor
        $dur = getVideoDuration($in);
        $f   = $dur > 0 ? $dur / $target : 1.0;
        $f   = max(0.5, min(2.0, $f));
        $cmd = escapeshellcmd(FFMPEG_PATH)
             . " -y -threads 0 -preset ultrafast -i "
             . escapeshellarg($in)
             . " -filter_complex \"[0:v]setpts=PTS/{$f}[v];[0:a]atempo={$f}[a]\""
             . " -map \"[v]\" -map \"[a]\" "
             . escapeshellarg($out);
    }
    shell_exec($cmd);
    return file_exists($out) ? $out : $in;
}

/** Estrae la durata di un video in secondi. */
function getVideoDuration(string $file): float {
    $out = shell_exec(escapeshellcmd(FFMPEG_PATH) . ' -i ' . escapeshellarg($file) . ' 2>&1');
    if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $out, $m)) {
        return $m[1]*3600 + $m[2]*60 + $m[3];
    }
    return 0;
}
