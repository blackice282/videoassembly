<?php
require_once __DIR__ . '/config.php';

/**
 * Concatena le clip in un unico video (fast concat).
 *
 * @param array  $inputs Array di percorsi file video
 * @param string $out    Percorso file video di output
 * @return string        Percorso file generato
 */
function applyTransitions(array $inputs, string $out): string {
    $list = getConfig('paths.temp') . '/concat_' . uniqid() . '.txt';
    $lines = [];
    foreach ($inputs as $path) {
        // escape interno di singole virgolette
        $lines[] = "file '" . str_replace("'", "\\'", $path) . "'";
    }
    file_put_contents($list, implode("\n", $lines));

    $cmd = escapeshellcmd(FFMPEG_PATH)
         . " -y -threads 0 -preset ultrafast -f concat -safe 0 -i "
         . escapeshellarg($list)
         . " -c copy "
         . escapeshellarg($out);
    shell_exec($cmd);

    unlink($list);

    // fallback: se non creato o vuoto, copia il primo clip
    if (!file_exists($out) || filesize($out) === 0) {
        copy($inputs[0], $out);
    }

    return $out;
}
