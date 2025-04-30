<?php
require_once 'config.php';
function applyVideoEffect($in, $out, $effect) {
    switch ($effect) {
        case 'bw': $filter = 'hue=s=0'; break;
        case 'vintage': $filter = 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131'; break;
        case 'contrast': $filter = 'eq=contrast=1.5:brightness=0.05'; break;
        default: return copy($in, $out);
    }
    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -i %s -vf "%s" -c:a copy %s',
        FFMPEG_PATH,
        escapeshellarg($in),
        $filter,
        escapeshellarg($out)
    );
    shell_exec($cmd);
    return file_exists($out) && filesize($out) > 0;
}
?>