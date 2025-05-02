<?php
require_once 'config.php';

function applyTransitions($inputs, $out) {
    $list = TEMP_DIR . '/concat_' . uniqid() . '.txt';
    $lines = array_map(fn($p) => "file '" . str_replace("'", "\\'", $p) . "'", $inputs);
    file_put_contents($list, implode("\n", $lines));

    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -f concat -safe 0 -i %s -c copy %s 2>&1',
        FFMPEG_PATH,
        escapeshellarg($list),
        escapeshellarg($out)
    );
    shell_exec($cmd);

    if (!file_exists($out) || filesize($out) === 0) {
        copy($inputs[0], $out);
    }

    unlink($list);
    return file_exists($out) && filesize($out) > 0;
}
?>
