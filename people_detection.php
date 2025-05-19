<?php
function detectMovingPeople($files, $cfg, $tempDir) {
    ensureDir($tempDir);
    $segs = [];
    foreach ($files as $file) {
        $dur = floatval(shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_helpers=1:nokey=1 ".escapeshellarg($file)));
        $count = ceil($dur / $cfg['min_duration']);
        for ($i=0;$i<$count;$i++) {
            $seg = $tempDir . 'seg_'.basename($file)."_{$i}.ts";
            exec(sprintf('ffmpeg -ss %.2f -i %s -t %.2f -c copy %s', $i*$cfg['min_duration'], escapeshellarg($file), $cfg['min_duration'], escapeshellarg($seg)));
            $segs[] = $seg;
        }
    }
    return $segs;
}
?>