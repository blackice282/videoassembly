<?php
// ffmpeg_script.php
require 'config.php';

function processVideo($input, $output, $duration) {
    $resolution = getConfig('ffmpeg.resolution');
    $durationSec = intval($duration) * 60;
    $cmd = sprintf(
        'ffmpeg -y -i %s -vf "scale=%s:force_original_aspect_ratio=decrease,pad=%s:(ow-iw)/2:(oh-ih)/2" -t %d %s',
        escapeshellarg($input), $resolution, $resolution, $durationSec, escapeshellarg($output)
    );
    exec($cmd, $out, $return);
    return $return === 0;
}
?>