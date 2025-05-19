<?php
function concatenateTsSegments($segments, $tempDir) {
    $listFile = $tempDir . '/concat_list.txt';
    $fp = fopen($listFile, 'w');
    foreach ($segments as $seg) {
        fwrite($fp, "file '" . str_replace("'", "\\'", $seg) . "'\n");
    }
    fclose($fp);
    $outTs = $tempDir . '/combined.ts';
    $cmd = 'ffmpeg -f concat -safe 0 -i ' . escapeshellarg($listFile) . ' -c copy ' . escapeshellarg($outTs);
    exec($cmd);
    return $outTs;
}

function applyTransitions($segments, $transCfg, $tempDir) {
    if (count($segments) < 2) {
        return concatenateTsSegments($segments, $tempDir);
    }
    $inputs = '';
    foreach ($segments as $seg) {
        $inputs .= ' -i ' . escapeshellarg($seg);
    }
    $filters = [];
    $duration = $transCfg['duration'];
    $offset = 0;
    foreach ($segments as $i => $seg) {
        if ($i === 0) continue;
        $filters[] = "[{$i}:v][{$i}:v]xfade=transition={$transCfg['type']}:duration={$duration}:offset={$offset}[v{$i}]";
        $offset += $duration;
    }
    $filterComplex = implode(';', $filters);
    $last = 'v' . (count($segments) - 1);
    $outTs = $tempDir . '/transitions.ts';
    $cmd = 'ffmpeg' . $inputs
         . ' -filter_complex "' . $filterComplex . '"'
         . ' -map "[' . $last . ']"'
         . ' -c:v copy ' . escapeshellarg($outTs);
    exec($cmd);
    return $outTs;
}

function concatenateWithTransitions($segments, $transCfg, $tempDir) {
    return applyTransitions($segments, $transCfg, $tempDir);
}
?>