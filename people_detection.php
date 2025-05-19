<?php
function detectMovingPeople($files, $config, $tempDir) {
    ensureDir($tempDir);
    $segments = [];
    foreach ($files as $file) {
        $duration = floatval(shell_exec(
            "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " 
            . escapeshellarg($file)
        ));
        $count = ceil($duration / $config['detection']['min_duration']);
        for ($i = 0; $i < $count; $i++) {
            $seg = $tempDir . basename($file) . "_{$i}.ts";
            exec(sprintf(
                'ffmpeg -ss %.2f -i %s -t %.2f -c copy %s',
                $i * $config['detection']['min_duration'],
                escapeshellarg($file),
                $config['detection']['min_duration'],
                escapeshellarg($seg)
            ));
            $segments[] = $seg;
        }
    }
    return $segments;
}
?>