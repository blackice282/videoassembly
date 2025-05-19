<?php
function concatenateTsSegments($segments, $tempDir) {
    $listFile = $tempDir . '/concat_list.txt';
    $fp = fopen($listFile, 'w');
    foreach ($segments as $seg) {
        fwrite($fp, "file '" . str_replace("'", "\\'", $seg) . "'\n");
    }
    fclose($fp);
    $outTs = $tempDir . '/combined.ts';
    $cmd = sprintf("ffmpeg -f concat -safe 0 -i %s -c copy %s",
        escapeshellarg($listFile), escapeshellarg($outTs));
    exec($cmd);
    return $outTs;
}

function applyTransitions($segments, $transCfg, $tempDir) {
    if (count($segments) < 2) {
        return concatenateTsSegments($segments, $tempDir);
    }
    // Build inputs
    $inputs = '';
    foreach ($segments as $i => $seg) {
        $inputs .= sprintf(" -i %s", escapeshellarg($seg));
    }
    // Build xfade filters
    $filters = [];
    $duration = $transCfg['duration'];
    $offset = 0;
    foreach ($segments as $i => $seg) {
        if ($i === 0) continue;
        $filters[] = sprintf("[%d:v][%d:v]xfade=transition=%s:duration=%.2f:offset=%.2f[v%d]",
            $i-1, $i, $transCfg['type'], $duration, $offset, $i);
        $offset += $duration;
    }
    $filterComplex = implode(";", $filters);
    $last = 'v' . (count($segments)-1);
    $outTs = $tempDir . '/transitions.ts';
    $cmd = sprintf(
        "ffmpeg%s -filter_complex "%s" -map "[%s]" -c:v copy %s",
        $inputs,
        $filterComplex,
        $last,
        escapeshellarg($outTs)
    );
    exec($cmd);
    return $outTs;
}

function concatenateWithTransitions($segments, $transCfg, $tempDir) {
    return applyTransitions($segments, $transCfg, $tempDir);
}
?>