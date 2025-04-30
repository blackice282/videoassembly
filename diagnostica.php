<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';

// Risponde in JSON
header('Content-Type: application/json');

/**
 * Controlla i codec e i filtri FFmpeg
 */
function checkFFmpeg() {
    $codecs  = shell_exec(FFMPEG_PATH . ' -codecs 2>&1');
    $filters = shell_exec(FFMPEG_PATH . ' -filters 2>&1');
    return [
        'libx264'      => strpos($codecs, 'libx264') !== false,
        'h264'         => strpos($codecs, 'h264')    !== false,
        'aac'          => strpos($codecs, 'aac')     !== false,
        'mp3'          => strpos($codecs, 'mp3')     !== false,
        'colorbalance' => strpos($filters, 'colorbalance') !== false,
        'unsharp'      => strpos($filters, 'unsharp')      !== false,
        'hue'          => strpos($filters, 'hue')          !== false,
        'eq'           => strpos($filters, 'eq')           !== false,
    ];
}

/**
 * Testa tutti gli effetti video supportati
 */
function generateEffectsReport() {
    $effects = ['none','bw','vintage','contrast'];
    $res     = [];
    $sample  = TEMP_DIR . '/diag_sample.mp4';

    // crea un breve video di test (solo video)
    shell_exec(sprintf(
      "%s -y -f lavfi -i testsrc=duration=1:size=320x240:rate=10 %s",
      FFMPEG_PATH,
      escapeshellarg($sample)
    ));

    foreach ($effects as $e) {
        $tmp = TEMP_DIR . "/diag_eff_{$e}.mp4";
        $ok  = applyVideoEffect($sample, $tmp, $e);
        $res[$e] = $ok;
        @unlink($tmp);
    }
    @unlink($sample);
    return $res;
}

/**
 * Testa download dell'audio da categoria
 */
function generateAudioReport() {
    $cats = ['emozionale'];
    $res  = [];
    foreach ($cats as $cat) {
        $audio = getRandomAudioFromCategory($cat);
        if ($audio) {
            $tmp = TEMP_DIR . "/diag_aud_{$cat}.mp3";
            $ok  = downloadAudio($audio['url'], $tmp);
            $res[$cat] = $ok;
            @unlink($tmp);
        } else {
            $res[$cat] = false;
        }
    }
    return $res;
}

// Costruiamo il report finale
echo json_encode([
    'ffmpeg'  => checkFFmpeg(),
    'effects' => generateEffectsReport(),
    'audio'   => generateAudioReport()
], JSON_PRETTY_PRINT);
