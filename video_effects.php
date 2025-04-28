<?php
require_once 'config.php';

/**
 * Applica un effetto video usando FFmpeg
 * 
 * @param string $inputFile Video di input
 * @param string $outputFile Video di output
 * @param string $effect Tipo di effetto ('bw', 'vintage', 'contrast')
 * @return bool true se successo, false altrimenti
 */
function applyVideoEffect($inputFile, $outputFile, $effect) {
    switch ($effect) {
        case 'bw':
            $filter = 'hue=s=0';
            break;
        case 'vintage':
            $filter = 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131';
            break;
        case 'contrast':
            $filter = 'eq=contrast=1.5:brightness=0.05';
            break;
        default:
            return copy($inputFile, $outputFile);
    }

    $cmd = sprintf('%s -y -i "%s" -vf "%s" -c:a copy "%s"',
        FFMPEG_PATH, $inputFile, $filter, $outputFile);
    shell_exec($cmd);

    return file_exists($outputFile) && filesize($outputFile) > 0;
}
?>
