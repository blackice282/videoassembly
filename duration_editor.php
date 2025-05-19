<?php
function adaptDuration($inTs, $tgt, $tempDir) {
    if($tgt<=0) return $inTs;
    $dur = floatval(shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ".escapeshellarg($inTs)));
    $out = $tempDir.'/adapted.ts';
    if($dur > $tgt) {
        exec(sprintf('ffmpeg -ss 0 -i %s -t %.2f -c copy %s', escapeshellarg($inTs), $tgt, escapeshellarg($out)));
        return $out;
    } elseif($dur < $tgt) {
        $times = ceil($tgt/$dur);
        $list=$tempDir.'/dup.txt';
        $fp=fopen($list,'w');
        for($i=0;$i<$times;$i++) fwrite($fp,"file '".str_replace("'","\\'",$inTs)."'\n");
        fclose($fp);
        $tmp=$tempDir.'/loop.ts';
        exec('ffmpeg -f concat -safe 0 -i '.escapeshellarg($list).' -c copy '.escapeshellarg($tmp));
        exec(sprintf('ffmpeg -ss 0 -i %s -t %.2f -c copy %s', escapeshellarg($tmp), $tgt, escapeshellarg($out)));
        return $out;
    }
    return $inTs;
}
?>