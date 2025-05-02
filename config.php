<?php
require_once 'config.php';

/**
 * Concatena clip specificate in array in un unico video
 *
 * @param array  $inputs Array di percorsi file video
 * @param string $out    Percorso file video di output
 */
function applyTransitions($inputs, $out) {
    // Crea file temporaneo con lista di clip
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $lines = array_map(function($path) {
        return "file '" . str_replace("'", "\\'", $path) . "'";
    }, $inputs);
    file_put_contents($list, implode("\n", $lines));

    // Esegue concatenazione veloce
    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s 2>&1',
        FFMPEG_PATH,
        escapeshellarg($list),
        escapeshellarg($out)
    );
    shell_exec($cmd);

    // Se il file non è stato creato, fallback: copia il primo clip
    if (!file_exists($out) || filesize($out) === 0) {
        copy($inputs[0], $out);
    }

    // Rimuove file lista
    unlink($list);

    return file_exists($out) && filesize($out) > 0;
}
?>
