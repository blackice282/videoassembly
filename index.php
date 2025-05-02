<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';
require_once 'privacy_manager.php';

// Genera job e file di log
$job     = uniqid();
$logFile = getConfig('paths.temp') . "/progress_{$job}.log";
file_put_contents($logFile, "START JOB $job at " . date('c') . "\n");

function logStep($msg) {
    global $logFile;
    file_put_contents($logFile, date('[H:i:s] ') . $msg . "\n", FILE_APPEND);
}

function createUploadsDir() {
    foreach ([getConfig('paths.uploads'), getConfig('paths.temp')] as $d) {
        if (!file_exists($d)) mkdir($d, 0777, true);
    }
    logStep("Created upload/temp directories");
}

function generateOutputName() {
    return 'render_' . date('Ymd_His') . '.mp4';
}

function processVideoChain($videos, $opts) {
    global $logFile;
    $processed = [];
    foreach ($videos as $v) {
        logStep("Processing file: " . basename($v));
        $w = $v;
        if ($opts['mode'] === 'detect_people') {
            logStep(" - applyPeopleDetection");
            $w = applyPeopleDetection($w);
        }
        if ($opts['duration'] > 0) {
            logStep(" - applyDurationEdit ({$opts['duration_method']})");
            $w = applyDurationEdit($w, $opts['duration'], $opts['duration_method']);
        }
        if ($opts['effect'] !== 'none') {
            logStep(" - applyVideoEffect ({$opts['effect']})");
            $tmp = getConfig('paths.temp') . "/effect_" . basename($w);
            if (applyVideoEffect($w, $tmp, $opts['effect'])) $w = $tmp;
        }
        if ($opts['privacy']) {
            logStep(" - applyFacePrivacy");
            $tmp = getConfig('paths.temp') . "/privacy_" . basename($w);
            if (applyFacePrivacy($w, $tmp)) {
                $w = $tmp;
                trackFile($w, basename($w), 'face_privacy');
            }
        }
        $processed[] = $w;
    }
    $final = getConfig('paths.uploads') . "/" . generateOutputName();
    logStep("Applying transitions to " . count($processed) . " clips");
    applyTransitions($processed, $final);
    if ($opts['audio'] !== 'none') {
        logStep(" - applyBackgroundAudio ({$opts['audio']})");
        $audio = getRandomAudioFromCategory($opts['audio']);
        $tmp   = str_replace('.mp4', '_aud.mp4', $final);
        if ($audio && downloadAudio($audio['url'], $t = getConfig('paths.temp') . '/aud.mp3')) {
            applyBackgroundAudio($final, $t, $tmp, 0.3);
            rename($tmp, $final);
        }
    }
    trackFile($final, basename($final), 'output');
    logStep("JOB COMPLETE, output: " . basename($final));
    return $final;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['videos'])) {
    createUploadsDir();
    set_time_limit(0);

    $opts = [
        'mode'            => $_POST['mode']            ?? 'simple',
        'duration'        => intval($_POST['duration']) * 60,
        'duration_method' => $_POST['duration_method'] ?? 'trim',
        'effect'          => $_POST['effect']          ?? 'none',
        'audio'           => $_POST['audio']           ?? 'none',
        'privacy'         => isset($_POST['privacy']),
    ];

    $upl = $_FILES['videos'];
    $clips = [];
    for ($i = 0; $i < count($upl['name']); $i++) {
        if ($upl['error'][$i] === UPLOAD_ERR_OK) {
            $dest = getConfig('paths.uploads') . '/vid_' . uniqid() . '_' . basename($upl['name'][$i]);
            move_uploaded_file($upl['tmp_name'][$i], $dest);
            trackFile($dest, $upl['name'][$i], 'upload');
            logStep("Uploaded " . basename($dest));
            $clips[] = $dest;
        }
    }

    $out = processVideoChain($clips, $opts);
    $fileName = basename($out);

    echo <<<HTML
<div style="padding:2rem;font-family:sans-serif;">
  <h2>✅ Avvio elaborazione…</h2>
  <pre id="log" style="background:#eee;padding:1rem;height:200px;overflow:auto;"></pre>
  <script>
    const job="{$job}";
    async function updateLog(){
      let txt = await fetch("status.php?job="+job).then(r=>r.text());
      document.getElementById("log").innerText = txt;
    }
    setInterval(updateLog,1000);
    updateLog();
  </script>
  <p style="margin-top:1rem;">
    Quando vedi "JOB COMPLETE", <a href="serve.php?file={$fileName}">clicca qui per scaricare</a>.
  </p>
</div>
HTML;
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><title>VideoAssembly</title></head>
<body>
  <form method="post" enctype="multipart/form-data" style="max-width:600px;margin:auto;padding:2rem;background:#fff;">
    <h1>VideoAssembly</h1>
    <label>Carica video:<br><input type="file" name="videos[]" multiple required></label><br>
    <label>Modalità:<br><select name="mode"><option value="simple">Semplice</option><option value="detect_people">Rileva Persone</option></select></label><br>
    <label>Durata (min):<br><input type="number" name="duration" value="3" min="1"></label><br>
    <label>Metodo:<br><select name="duration_method"><option value="trim">Taglia</option><option value="speed">Accelera</option></select></label><br>
    <label>Effetto:<br><select name="effect"><option value="none">Nessuno</option><option value="bw">Bianco e Nero</option><option value="vintage">Vintage</option><option value="contrast">Contrasto</option></select></label><br>
    <label>Audio:<br><select name="audio"><option value="none">Nessuno</option><option value="emozionale">Emozionale</option></select></label><br>
    <label><input type="checkbox" name="privacy" checked> Applica emoji sui volti</label><br>
    <button type="submit">Avvia Montaggio</button>
  </form>
</body>
</html>
