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
require_once 'privacy_manager.php';   // <— aggiunto
require_once 'debug.php';            // <— aggiunto

// Funzione di logging
function debugLog($msg, $lvl='info') {
    if (getConfig('system.debug')) {
        debugLog($msg, $lvl);
    }
}

function createUploadsDir() {
    foreach ([getConfig('paths.uploads'), getConfig('paths.temp')] as $d) {
        if (!file_exists($d)) mkdir($d, 0777, true);
    }
}

function generateOutputName() {
    return 'render_' . date('Ymd_His') . '.mp4';
}

function processVideoChain($videos, $opts) {
    $out = [];
    foreach ($videos as $v) {
        $w = $v;
        if ($opts['mode']==='detect_people') {
            $w = applyPeopleDetection($w);
        }
        if ($opts['duration']>0) {
            $w = applyDurationEdit($w, $opts['duration'], $opts['duration_method']);
        }
        if ($opts['effect']!=='none') {
            $tmp = getConfig('paths.temp').'/eff_'.basename($w);
            if (applyVideoEffect($w, $tmp, $opts['effect'])) $w = $tmp;
        }
        if ($opts['privacy']) {
            $tmp = getConfig('paths.temp').'/priv_'.basename($w);
            if (applyFacePrivacy($w, $tmp)) {
                $w = $tmp;
                trackFile($w, basename($w), 'face_privacy'); // tracciatura privacy
            }
        }
        $out[] = $w;
    }
    $final = getConfig('paths.uploads').'/'.generateOutputName();
    applyTransitions($out, $final);
    if ($opts['audio']!=='none') {
        $audio = getRandomAudioFromCategory($opts['audio']);
        $tmp   = str_replace('.mp4','_aud.mp4',$final);
        if ($audio && downloadAudio($audio['url'], $ta=getConfig('paths.temp').'/aud.mp3')) {
            if (applyBackgroundAudio($final, $ta, $tmp, 0.3)) {
                rename($tmp, $final);
            }
        }
    }
    trackFile($final, basename($final), 'output');
    return $final;
}

// Se invio del form
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['videos'])) {
    createUploadsDir();
    set_time_limit(600);
    $opts = [
        'mode'            => $_POST['mode'] ?? 'simple',
        'duration'        => intval($_POST['duration'] ?? 0)*60,
        'duration_method' => $_POST['duration_method'] ?? 'trim',
        'effect'          => $_POST['effect'] ?? 'none',
        'audio'           => $_POST['audio'] ?? 'none',
        'privacy'         => isset($_POST['privacy']),
    ];
    $uploaded = $_FILES['videos'];
    $tempVideos = [];
    for ($i=0; $i<count($uploaded['name']); $i++) {
        if ($uploaded['error'][$i]===UPLOAD_ERR_OK) {
            $dest = getConfig('paths.uploads').'/'.uniqid('vid_').'_'.$uploaded['name'][$i];
            move_uploaded_file($uploaded['tmp_name'][$i], $dest);
            trackFile($dest, $uploaded['name'][$i], 'upload');
            $tempVideos[] = $dest;
        }
    }
    $out = processVideoChain($tempVideos, $opts);
    $fname = basename($out);
    echo "<div style='padding:2rem;font-family:sans-serif;'>
            <h2>✅ Elaborazione completata</h2>
            <p><a href='serve.php?file={$fname}'
                  style='display:inline-block;padding:1rem 2rem;background:#28a745;color:#fff;
                         text-decoration:none;border-radius:6px;'>Scarica Video</a></p>
          </div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>VideoAssembly</title>
<style>
  body{background:#f4f4f4;margin:0;font-family:sans-serif;padding:2rem;}
  form{max-width:600px;margin:auto;background:#fff;padding:2rem;border-radius:8px;
        box-shadow:0 0 10px rgba(0,0,0,.1);}
  label{display:block;margin-top:1rem;}
  input,select{width:100%;padding:.5rem;margin-top:.3rem;}
  button{margin-top:1.5rem;width:100%;padding:.8rem;background:#007bff;color:#fff;
         border:none;border-radius:6px;cursor:pointer;font-size:1rem;}
  button:hover{background:#0056b3;}
</style>
</head>
<body>
  <h1 style="text-align:center;">VideoAssembly</h1>
  <form method="post" enctype="multipart/form-data">
    <label>Carica video:
      <input type="file" name="videos[]" multiple required>
    </label>
    <label>Modalità:
      <select name="mode">
        <option value="simple">Semplice</option>
        <option value="detect_people">Rileva Persone</option>
      </select>
    </label>
    <label>Durata (minuti):
      <input type="number" name="duration" value="3" min="1">
    </label>
    <label>Metodo durata:
      <select name="duration_method">
        <option value="trim">Taglia</option>
        <option value="speed">Accelera</option>
      </select>
    </label>
    <label>Effetto video:
      <select name="effect">
        <option value="none">Nessuno</option>
        <option value="bw">Bianco e Nero</option>
        <option value="vintage">Vintage</option>
        <option value="contrast">Contrasto</option>
      </select>
    </label>
    <label>Audio sfondo:
      <select name="audio">
        <option value="none">Nessuno</option>
        <option value="emozionale">Emozionale</option>
      </select>
    </label>
    <label><input type="checkbox" name="privacy" checked> Applica emoji sui volti</label>
    <button type="submit">Avvia Montaggio</button>
  </form>
  <p style="text-align:center;margin-top:2rem;">
    <a href="diagnostica.php" style="color:#666;font-size:.9rem;">Diagnostica sistema</a>
  </p>
</body>
</html>
