<?php
require_once __DIR__ . '/config.php';

/**
 * Concatena le clip in un unico video (fast concat)
 *
 * @param array  $inputs Array di percorsi file video
 * @param string $out    Percorso file video di output
 * @return string        Percorso del file generato
 */
function applyTransitions($inputs, $out) {
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $lines = array_map(function($path) {
        return "file '" . str_replace("'", "\\'", $path) . "'";
    }, $inputs);
    file_put_contents($list, implode("\n", $lines));

    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s',
        FFMPEG_PATH,
        escapeshellarg($list),
        escapeshellarg($out)
    );
    shell_exec($cmd);
    unlink($list);

    return $out;
}
?>
