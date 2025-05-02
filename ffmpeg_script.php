<?php
require_once __DIR__ . '/config.php';

/**
 * Concatena clip specificate in array in un unico video
 *
 * @param array  $inputs Array di percorsi file video
 * @param string $out    Percorso file video di output
 */
function applyTransitions($inputs, $out) {
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $lines = array_map(function($path) {
        return "file '" . str_replace("'", "\\'", $path) . "'";
    }, $inputs);
    file_put_contents($list, implode("\\n", $lines));

    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s',
        FFMPEG_PATH,
        escapeshellarg($list),
        escapeshellarg($out)
    );
    shell_exec($cmd);

    unlink($list);

    // Ritorna sempre il path di output
    return $out;
}
?>
