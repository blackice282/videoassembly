<?php
function detectMovingPeople($files, $detectionCfg, $tempDir) {
    $segments = [];
    foreach ($files as $file) {
        // scene detection (dummy)
        $duration = floatval(shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file)));
        $minDur = $detectionCfg['min_duration'];
        $gap    = $detectionCfg['max_gap'];
        $fps    = $detectionCfg['frame_rate'];
        $conf   = $detectionCfg['confidence'];

        // split into fixed-size chunks
        $count = ceil($duration / $minDur);
        for ($i = 0; $i < $count; $i++) {
            $start = $i * $minDur;
            $segFile = $tempDir . "seg_" . basename($file) . "_{$i}.ts";
            // extract segment
            $cmd = sprintf(
                'ffmpeg -ss %.2f -i %s -t %.2f -c copy %s',
                $start, escapeshellarg($file), $minDur, escapeshellarg($segFile)
            );
            exec($cmd);
            $segments[] = $segFile;
        }
    }
    return $segments;
}
?>