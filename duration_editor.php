<?php
require_once 'config.php';
function applyDurationEdit($in, $target, $method='trim'){
    $out = TEMP_DIR . '/dur_' . uniqid() . '.mp4';
    if($method==='trim'){
        shell_exec(sprintf(
            '%s -y -threads 0 -preset ultrafast -i %s -t %d -c copy %s',
            FFMPEG_PATH,escapeshellarg($in),$target,escapeshellarg($out)
        ));
    } else {
        $dur = getVideoDuration($in);
        $factor = $dur/$target;
        shell_exec(sprintf(
            '%s -y -threads 0 -preset ultrafast -i %s -filter_complex "[0:v]setpts=PTS/%.2f[v];[0:a]atempo=%.2f[a]" -map "[v]" -map "[a]" %s',
            FFMPEG_PATH,escapeshellarg($in),$factor,min(max($factor,0.5),2.0),escapeshellarg($out)
        ));
    }
    return file_exists($out) ? $out : $in;
}
function getVideoDuration($file){
    $o = shell_exec(sprintf('%s -i %s 2>&1',FFMPEG_PATH,escapeshellarg($file)));
    if(preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/',$o,$m)){
        return $m[1]*3600+$m[2]*60+$m[3];
    }
    return 0;
}
?>