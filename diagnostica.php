<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ffmpeg_script.php';
require_once __DIR__ . '/video_effects.php';
require_once __DIR__ . '/audio_manager.php';

header('Content-Type: application/json');

// 1) Genera un breve sample video in TEMP_DIR
$sample = getConfig('paths.temp') . '/diagnostic_sample.mp4';
@unlink($sample);
$cmdSample = sprintf(
    '%s -y -f lavfi -i testsrc=duration=1:size=320x240:rate=10 %s',
    FFMPEG_PATH,
    escapeshellarg($sample)
);
shell_exec($cmdSample);

// 2) Controlla codec e filtri FFmpeg
function checkFFmpeg() {
    $c = shell_exec(FFMPEG_PATH . ' -codecs 2>&1');
    $f = shell_exec(FFMPEG_PATH . ' -filters 2>&1');
    return [
        'libx264'      => strpos($c, 'libx264') !== false,
        'h264'         => strpos($c, 'h264')    !== false,
        'aac'          => strpos($c, 'aac')     !== false,
        'mp3'          => strpos($c, 'mp3')     !== false,
        'colorbalance' => strpos($f, 'colorbalance') !== false,
        'unsharp'      => strpos($f, 'unsharp')      !== false,
        'hue'          => strpos($f, 'hue')          !== false,
        'eq'           => strpos($f, 'eq')           !== false,
    ];
}

// 3) Test effetti video
function generateEffectsReport(string $sample): array {
    $effects = ['none','bw','vintage','contrast'];
    $res     = [];
    foreach ($effects as $e) {
        $out = getConfig('paths.temp') . "/diag_eff_{$e}.mp4";
        @unlink($out);
        $ok = applyVideoEffect($sample, $out, $e);
        $res[$e] = $ok && file_exists($out) && filesize($out) > 0;
        @unlink($out);
    }
    return $res;
}

// 4) Test download audio
function generateAudioReport(): array {
    $cats = ['emozionale'];
    $res  = [];
    foreach ($cats as $cat) {
        $audio = getRandomAudioFromCategory($cat);
        if (!$audio) {
            $res[$cat] = false;
            continue;
        }
        $tmp = getConfig('paths.temp') . "/diag_aud_{$cat}.mp3";
        @unlink($tmp);
        $ok = downloadAudio($audio['url'], $tmp);
        $res[$cat] = $ok && file_exists($tmp) && filesize($tmp) > 0;
        @unlink($tmp);
    }
    return $res;
}

// 5) Cleanup sample
$effectsReport = [];
$audioReport   = [];
if (file_exists($sample)) {
    $effectsReport = generateEffectsReport($sample);
    $audioReport   = generateAudioReport();
    @unlink($sample);
}

echo json_encode([
    'ffmpeg'  => checkFFmpeg(),
    'effects' => $effectsReport,
    'audio'   => $audioReport
], JSON_PRETTY_PRINT);
