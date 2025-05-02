<?php
require_once __DIR__ . '/config.php';

/**
 * Concatena più clip in un unico video usando filter_complex concat.
 *
 * @param array  $inputs Array di percorsi file video
 * @param string $out    Percorso file video di output
 * @return string        Percorso del file generato
 */
function applyTransitions(array $inputs, string $out): string {
    $count = count($inputs);
    if ($count === 0) {
        return '';
    }

    // Costruisci l'elenco degli -i
    $inputArgs = '';
    foreach ($inputs as $path) {
        $inputArgs .= ' -i ' . escapeshellarg($path);
    }

    // Costruisci la parte filter_complex
    // es. [0:v:0][0:a:0][1:v:0][1:a:0]…concat=n=2:v=1:a=1[v][a]
    $filter = '';
    for ($i = 0; $i < $count; $i++) {
        $filter .= "[$i:v:0][$i:a:0]";
    }
    $filter .= "concat=n={$count}:v=1:a=1[v][a]";

    // Componi il comando FFmpeg
    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast%s -filter_complex "%s" -map "[v]" -map "[a]" %s 2>&1',
        FFMPEG_PATH,
        $inputArgs,
        $filter,
        escapeshellarg($out)
    );

    // Esegui e, se vuoi debug, salva l’output in log
    shell_exec($cmd);

    return $out;
}
