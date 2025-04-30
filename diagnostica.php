<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';

// Risponde in JSON
header('Content-Type: application/json');

// FFmpeg capabilities
function checkFFmpeg() {
    $codecs = shell_exec(FFMPEG_PATH . ' -codecs 2>&1');
    $filters = shell_exec(FFMPEG_PATH . ' -filters 2>&1');
    return [
        'libx264'        => strpos($codecs, 'libx264') !== false,
        'h264'           => strpos($codecs, 'h264') !== false,
        'aac'            => strpos($codecs, 'aac') !== false,
        'mp3'            => strpos($codecs, 'mp3') !== false,
        'colorbalance'   => strpos($filters, 'colorbalance') !== false,
        'unsharp'        => strpos($filters, 'unsharp') !== false,
        'hue'            => strpos($filters, 'hue') !== false,
        'eq'             => strpos($filters, 'eq') !== false,
    ];
}

// Test effetti video
function generateEffectsReport($input) {
    $effects = ['none','bw','vintage','contrast'];
    $res = [];
    foreach ($effects as $e) {
        $tmp = TEMP_DIR . "/diag_{$e}.mp4";
        $ok = applyVideoEffect($input, $tmp, $e);
        $res[$e] = $ok;
        if (file_exists($tmp)) unlink($tmp);
    }
    return $res;
}

// Test audio mix
function generateAudioReport($input) {
    $audioCats = ['emozionale'];
    $res = [];
    foreach ($audioCats as $cat) {
        $audio = getRandomAudioFromCategory($cat);
        $tmpA  = TEMP_DIR . '/diag_track.mp3';
        $out   = TEMP_DIR . "/diag_audio_{$cat}.mp4";
        $ok = $audio && downloadAudio($audio['url'], $tmpA)
              && applyBackgroundAudio($input, $tmpA, $out, 0.3);
        $res[$cat] = $ok;
        if (file_exists($out)) unlink($out);
    }
    return $res;
}

$sample = TEMP_DIR . '/diagnostic_sample.mp4';
// genera un breve clip da ffmpeg per test
shell_exec(sprintf(
  '%s -y -f lavfi -i testsrc=duration=2:size=320x240:rate=10 %s',
  FFMPEG_PATH, escapeshellarg($sample)
));

echo json_encode([
    'ffmpeg'  => checkFFmpeg(),
    'effects' => generateEffectsReport($sample),
    'audio'   => generateAudioReport($sample)
], JSON_PRETTY_PRINT);
