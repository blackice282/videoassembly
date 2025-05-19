<?php
function adaptDuration($inputTs, $targetDuration, $tempDir) {
    if ($targetDuration <= 0) {
        return $inputTs;
    }
    // Get actual duration
    $actualDur = floatval(shell_exec(
        "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "
        . escapeshellarg($inputTs)
    ));
    $outTs = $tempDir . '/duration_adapted.ts';
    if ($actualDur > $targetDuration) {
        // Trim
        $cmd = sprintf("ffmpeg -ss 0 -i %s -t %.2f -c copy %s",
            escapeshellarg($inputTs), $targetDuration, escapeshellarg($outTs));
        exec($cmd);
        return $outTs;
    } elseif ($actualDur < $targetDuration) {
        // Loop segments
        $times = ceil($targetDuration / $actualDur);
        $listFile = $tempDir . '/duration_concat_list.txt';
        $fp = fopen($listFile, 'w');
        for ($i = 0; $i < $times; $i++) {
            fwrite($fp, "file '" . str_replace("'", "\\'", $inputTs) . "'\n");
        }
        fclose($fp);
        $tempCombined = $tempDir . '/duration_loop.ts';
        $cmd = sprintf("ffmpeg -f concat -safe 0 -i %s -c copy %s", 
            escapeshellarg($listFile), escapeshellarg($tempCombined));
        exec($cmd);
        // Trim to exact
        $cmd2 = sprintf("ffmpeg -ss 0 -i %s -t %.2f -c copy %s",
            escapeshellarg($tempCombined), $targetDuration, escapeshellarg($outTs));
        exec($cmd2);
        return $outTs;
    }
    return $inputTs;
}
?>