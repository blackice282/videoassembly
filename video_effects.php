<?php
require_once __DIR__ . '/config.php';

/**
 * Applica un effetto video semplice con FFmpeg.
 *
 * @param string $in     File di input
 * @param string $out    File di output
 * @param string $effect Nome effetto: none, bw, vintage, contrast
 * @return bool          True se l'output esiste e non è vuoto
 */
function applyVideoEffect(string $in, string $out, string $effect): bool {
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
            return copy($in, $out);
    }
    $cmd = escapeshellcmd(FFMPEG_PATH)
         . " -y -threads 0 -preset ultrafast -i "
         . escapeshellarg($in)
         . " -vf "
         . escapeshellarg($filter)
         . " -c:a copy "
         . escapeshellarg($out);
    shell_exec($cmd);
    return file_exists($out) && filesize($out) > 0;
}
