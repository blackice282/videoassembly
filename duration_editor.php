<?php
// duration_editor.php

function duration_trim_and_merge(array $inputs, int $maxSeconds, string $outputDir): ?string {
    $txtList = tempnam(sys_get_temp_dir(), 'fflist');
    $fp = fopen($txtList, 'w');
    foreach ($inputs as $file) {
        fprintf($fp, "file '%s'\n", addslashes(realpath($file)));
    }
    fclose($fp);

    $mergedTs = $outputDir . '/' . uniqid('merged_') . '.ts';
    $trimmedMp4 = $outputDir . '/' . uniqid('out_') . '.mp4';

    // 1) concat in formato TS (lossless)
    $cmd1 = "ffmpeg -y -f concat -safe 0 -i $txtList -c copy -bsf:v h264_mp4toannexb $mergedTs";
    exec($cmd1, $o1, $st1);
    if ($st1 !== 0) {
        @unlink($txtList);
        return null;
    }

    // 2) trim alla durata richiesta
    $cmd2 = "ffmpeg -y -i $mergedTs -t $maxSeconds -c copy $trimmedMp4";
    exec($cmd2, $o2, $st2);
    @unlink($mergedTs);
    @unlink($txtList);
    if ($st2 !== 0) {
        return null;
    }

    return $trimmedMp4;
}
