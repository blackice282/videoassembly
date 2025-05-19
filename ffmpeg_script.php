<?php
function processVideo($inputTs, $outputMp4, $outputDir) {
    $cmd = sprintf(
        'ffmpeg -i %s -c:v %s -preset %s -crf %d -c:a %s %s',
        escapeshellarg($inputTs),
        getConfig()['codec']['video_codec'],
        getConfig()['codec']['preset'],
        getConfig()['codec']['crf'],
        getConfig()['codec']['audio_codec'],
        escapeshellarg($outputMp4)
    );
    exec($cmd);
    // thumbnail
    $thumb = $outputDir . basename($outputMp4, '.mp4') . '.jpg';
    exec(sprintf(
        'ffmpeg -i %s -ss 00:00:01 -vframes 1 %s',
        escapeshellarg($outputMp4),
        escapeshellarg($thumb)
    ));
}
?>