<?php
require_once __DIR__ . '/config.php';

/** Catalogo di base, sostituibile con le tue tracce. */
function getRandomAudioFromCategory(string $cat): ?array {
    $catalog = [
        'emozionale' => [
            ['name'=>'Piano','url'=>'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3'],
            ['name'=>'Piano2','url'=>'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3']
        ],
    ];
    return $catalog[$cat][array_rand($catalog[$cat])] ?? null;
}

/** Scarica via cURL e salva su disco. */
function downloadAudio(string $url, string $path): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'PHP VideoAssembly',
        CURLOPT_TIMEOUT        => 30,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $data !== false) {
        file_put_contents($path, $data);
        return filesize($path) > 0;
    }
    return false;
}

/** Miscelazione audio su video. */
function applyBackgroundAudio(string $video, string $audio, string $out, float $vol = 0.3): bool {
    $cmd = escapeshellcmd(FFMPEG_PATH)
         . " -y -threads 0 -preset ultrafast -i "
         . escapeshellarg($video)
         . " -i "
         . escapeshellarg($audio)
         . " -filter_complex \"[1:a]volume={$vol}[a1];[0:a][a1]amix=inputs=2:duration=first\""
         . " -map 0:v -map \"[a1]\" -c:v copy -shortest "
         . escapeshellarg($out);
    shell_exec($cmd);
    return file_exists($out) && filesize($out) > 0;
}
