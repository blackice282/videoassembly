<?php
function concatenateTsSegments($segments, $tempDir) {
    ensureDir($tempDir);
    $list = $tempDir.'/list.txt';
    $fp = fopen($list,'w');
    foreach ($segments as $s) {
        fwrite($fp, "file '".str_replace("'","\\'",$s)."'\n");
    }
    fclose($fp);
    $out = $tempDir.'/combined.ts';
    exec('ffmpeg -f concat -safe 0 -i '.escapeshellarg($list).' -c copy '.escapeshellarg($out));
    return $out;
}
function applyTransitions($segments, $cfg, $tempDir) {
    if(count($segments)<2) return concatenateTsSegments($segments,$tempDir);
    $inputs=''; $filters=[]; $offset=0;
    foreach($segments as $i=>$s){
        $inputs.=' -i '.escapeshellarg($s);
        if($i>0){
            $filters[]="[{$i-1}:v][{$i}:v]xfade=transition={$cfg['type']}:duration={$cfg['duration']}:offset={$offset}[v{$i}]";
            $offset+=$cfg['duration'];
        }
    }
    $fx = implode(';',$filters);
    $out=$tempDir.'/transitions.ts';
    exec('ffmpeg'.$inputs.' -filter_complex '.escapeshellarg($fx).' -map [v'.(count($segments)-1).'] -c:v copy '.escapeshellarg($out));
    return $out;
}
function concatenateWithTransitions($segs,$cfg,$tempDir){return applyTransitions($segs,$cfg,$tempDir);}
?>