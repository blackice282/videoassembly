<?php
function concatenateTsSegments($segments, $tempDir) {
    ensureDir($tempDir);
    $listFile = "{$tempDir}/list.txt";
    $fp = fopen($listFile, 'w');
    foreach ($segments as $seg) {
        fwrite($fp, "file '" . str_replace("'", "\'", $seg) . "\n");
    }
    fclose($fp);
    $outTs = "{$tempDir}/combined.ts";
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outTs);
    exec($cmd);
    return $outTs;
}

function applyTransitions($segments, $cfg, $tempDir) {
    ensureDir($tempDir);
    if (count($segments) < 2) {
        return concatenateTsSegments($segments, $tempDir);
    }
    $inputs = '';
    $filters = [];
    $offset = 0;
    foreach ($segments as $i => $seg) {
        $inputs .= " -i " . escapeshellarg($seg);
        if ($i > 0) {
            $filters[] = sprintf(
                "[%d:v][%d:v]xfade=transition=%s:duration=%.2f:offset=%.2f[v%d]",
                $i-1,
                $i,
                $cfg['type'],
                $cfg['duration'],
                $offset,
                $i
            );
            $offset += $cfg['duration'];
        }
    }
    $filterComplex = implode(";", $filters);
    $map = "[v" . (count($segments) - 1) . "]";
    $outTs = "{$tempDir}/transitions.ts";
    $cmd = "ffmpeg{$inputs} -filter_complex " . escapeshellarg($filterComplex) . " -map " . escapeshellarg($map) . " -c:v copy " . escapeshellarg($outTs);
    exec($cmd);
    return $outTs;
}

function concatenateWithTransitions($segments, $cfg, $tempDir) {
    return applyTransitions($segments, $cfg, $tempDir);
}
?>