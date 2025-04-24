<?php
require_once 'config.php';

/**
 * Applica un effetto video al file di input
 * 
 * @param string $input Path del video originale
 * @param string $output Path del video elaborato
 * @param string $effect Tipo di effetto ('bw', 'vintage', 'contrast')
 * @return bool true se successo, false altrimenti
 */
function applyVideoEffect($input, $output, $effect = 'none') {
    switch ($effect) {
        case 'bw': // Bianco e nero
            $filter = 'hue=s=0';
            break;
        case 'vintage': // Stile vintage/seppia
            $filter = 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131';
            break;
        case 'contrast': // Più contrasto
            $filter = 'eq=contrast=1.5:brightness=0.05';
            break;
        default:
            return copy($input, $output);
    }

    $cmd = sprintf('%s -y -i "%s" -vf "%s" -c:a copy "%s"', FFMPEG_PATH, $input, $filter, $output);
    shell_exec($cmd);
    return file_exists($output) && filesize($output) > 0;
}
