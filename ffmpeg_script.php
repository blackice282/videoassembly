<?php
function applyTransitions($inputs, $out){
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $txt = implode("\n", array_map(fn($p)=>"file '".str_replace("'","\\'",\$p)."'", \$inputs));
    file_put_contents($list, $txt);
    shell_exec(sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s',
        FFMPEG_PATH,escapeshellarg($list),escapeshellarg($out)
    ));
    unlink($list);
}
?>