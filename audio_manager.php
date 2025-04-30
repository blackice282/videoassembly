<?php
require_once 'config.php';
function getRandomAudioFromCategory($cat){
    $catalog = ['emozionale'=>[
        ['name'=>'Piano','url'=>'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3'],
        ['name'=>'Piano2','url'=>'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3']
    ]];
    return $catalog[$cat][array_rand($catalog[$cat])] ?? null;
}
function downloadAudio($url, $path){
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>'PHP VideoAssembly',
        CURLOPT_TIMEOUT=>30
    ]);
    $data = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($http===200 && $data!==false){
        file_put_contents($path, $data);
        return filesize($path)>0;
    }
    return false;
}
function applyBackgroundAudio($vid, $aud, $out, $vol=0.3){
    $cmd = sprintf(
        '%s -y -threads 0 -preset ultrafast -i %s -i %s -filter_complex "[1:a]volume=%f[a1];[0:a][a1]amix=inputs=2:duration=first" -map 0:v -map "[a1]" -c:v copy -shortest %s',
        FFMPEG_PATH,escapeshellarg($vid),escapeshellarg($aud),$vol,escapeshellarg($out)
    );
    shell_exec($cmd);
    return file_exists($out) && filesize($out)>0;
}
?>