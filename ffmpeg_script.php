<?php
function processVideo($inputTs, $outputMp4, $outputDir) {
    $cfg = require __DIR__ . '/config.php';
    $cmd = sprintf('ffmpeg -i %s -c:v %s -preset %s -crf %d -c:a %s %s',
        escapeshellarg($inputTs),
        $cfg['codec']['video_codec'],
        $cfg['codec']['preset'],
        $cfg['codec']['crf'],
        $cfg['codec']['audio_codec'],
        escapeshellarg($outputMp4)
    );
    exec($cmd);
    $thumb = $outputDir . basename($outputMp4, '.mp4') . '.jpg';
    exec(sprintf('ffmpeg -i %s -ss 00:00:01 -vframes 1 %s',
        escapeshellarg($outputMp4),
        escapeshellarg($thumb)
    ));
}
?>