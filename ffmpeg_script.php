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
    // Crea il file di lista
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $lines = array_map(function($path) {
        return "file '" . str_replace(\"'\", \"\\\\'\", $path) . "'";
    }, $inputs);
    file_put_contents($list, implode(\"\\n\", $lines));

    // Esegue la concatenazione
    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s 2>&1',
        FFMPEG_PATH,
        escapeshellarg($list),
        escapeshellarg($out)
    );
    shell_exec($cmd);

    // Rimuove il file lista
    unlink($list);

    // Fallback: se non creato, copia il primo input
    if (!file_exists($out) || filesize($out) === 0) {
        copy($inputs[0], $out);
    }

    return $out;
}
?>
