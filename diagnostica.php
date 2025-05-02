<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ffmpeg_script.php';
require_once __DIR__ . '/video_effects.php';
require_once __DIR__ . '/audio_manager.php';

header('Content-Type: application/json');

function checkFFmpeg() {
    $c = shell_exec(FFMPEG_PATH . ' -codecs 2>&1');
    $f = shell_exec(FFMPEG_PATH . ' -filters 2>&1');
    return [
        'libx264'=>strpos($c,'libx264')!==false,
        'h264'   =>strpos($c,'h264')!==false,
        'aac'    =>strpos($c,'aac')!==false,
        'mp3'    =>strpos($c,'mp3')!==false,
        'unsharp'=>strpos($f,'unsharp')!==false,
    ];
}

$effects=['none','bw','vintage','contrast'];
$effR=[];
foreach($effects as $e){
    $tmp=TEMP_DIR."/diag_{$e}.mp4";
    applyVideoEffect(TEMP_DIR."/sample.mp4",$tmp,$e);
    $effR[$e]=file_exists($tmp);
    @unlink($tmp);
}

echo json_encode(['ffmpeg'=>checkFFmpeg(),'effects'=>$effR,'audio'=>true], JSON_PRETTY_PRINT);
