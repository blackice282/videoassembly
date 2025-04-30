<?php
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

function createUploadsDir() {
    if (!file_exists(getConfig('paths.uploads', 'uploads'))) {
        mkdir(getConfig('paths.uploads', 'uploads'), 0777, true);
    }
    if (!file_exists(getConfig('paths.temp', 'temp'))) {
        mkdir(getConfig('paths.temp', 'temp'), 0777, true);
    }
}

function generateOutputName() {
    return 'render_' . date('Ymd_His') . '.mp4';
}

function processVideoChain($videos, $options) {
    $processedVideos = [];

    foreach ($videos as $video) {
        $working = $video;

        if ($options['mode'] === 'detect_people') {
            $working = applyPeopleDetection($working);
        }

        if ($options['duration'] > 0) {
            $working = applyDurationEdit($working, $options['duration'], $options['duration_method']);
        }

        if ($options['effect'] !== 'none') {
            $tmp = getConfig('paths.temp') . "/effect_" . basename($working);
            if (applyVideoEffect($working, $tmp, $options['effect'])) {
                $working = $tmp;
            }
        }

        if ($options['privacy']) {
            $tmp = getConfig('paths.temp') . "/privacy_" . basename($working);
            if (applyFacePrivacy($working, $tmp)) {
                $working = $tmp;
            }
        }

        $processedVideos[] = $working;
    }

    $final = getConfig('paths.uploads', 'uploads') . "/" . generateOutputName();
    applyTransitions($processedVideos, $final);

    if ($options['audio'] !== 'none') {
        $audio = getRandomAudioFromCategory($options['audio']);
        $temp = str_replace('.mp4', '_audio.mp4', $final);
        if ($audio && downloadAudio($audio['url'], $tmpAudio = getConfig('paths.temp') . '/track.mp3')) {
            if (applyBackgroundAudio($final, $tmpAudio, $temp, 0.3)) {
                rename($temp, $final);
            }
        }
    }

    return $final;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["videos"])) {
    createUploadsDir();
    set_time_limit(600);

    $options = [
        'mode' => $_POST['mode'] ?? 'simple',
        'duration' => isset($_POST['duration']) ? intval($_POST['duration']) * 60 : 0,
        'duration_method' => $_POST['duration_method'] ?? 'trim',
        'effect' => $_POST['effect'] ?? 'none',
        'audio' => $_POST['audio'] ?? 'none',
        'privacy' => isset($_POST['privacy']),
    ];

    $uploaded = $_FILES["videos"];
    $tempVideos = [];

    for ($i = 0; $i < count($uploaded["name"]); $i++) {
        if ($uploaded["error"][$i] === UPLOAD_ERR_OK) {
            $name = basename($uploaded["name"][$i]);
            $tmpPath = $uploaded["tmp_name"][$i];
            $destPath = getConfig('paths.uploads', 'uploads') . '/' . uniqid("video_") . "_" . $name;
            move_uploaded_file($tmpPath, $destPath);
            $tempVideos[] = $destPath;
        }
    }

    $result = processVideoChain($tempVideos, $options);
    $fileName = basename($result);

    echo "<div style='font-family:sans-serif;padding:2rem;'><h2>✅ Video Elaborato!</h2>";
    echo "<p><a href='serve.php?file=$fileName' style='background:#28a745;color:white;padding:1rem 2rem;text-decoration:none;border-radius:6px;'>Scarica il video</a></p></div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>VideoAssembly</title>
  <style>
    body { font-family: sans-serif; background: #f4f4f4; margin: 0; padding: 2rem; }
    form { background: white; padding: 2rem; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    h1 { text-align: center; }
    label { display: block; margin-top: 1rem; }
    input, select { width: 100%; padding: 0.5rem; margin-top: 0.3rem; }
    button { margin-top: 2rem; padding: 0.8rem; width: 100%; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
    button:hover { background: #0056b3; }
  </style>
</head>
<body>
  <h1>VideoAssembly</h1>
  <form method="post" enctype="multipart/form-data">
    <label>Carica uno o più video:
      <input type="file" name="videos[]" multiple required>
    </label>
    <label>Modalità:
      <select name="mode">
        <option value="simple">Semplice</option>
        <option value="detect_people">Rileva Persone</option>
      </select>
    </label>
    <label>Durata massima (in minuti):
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
    <label>Audio di sottofondo:
      <select name="audio">
        <option value="none">Nessuno</option>
        <option value="emozionale">Emozionale</option>
      </select>
    </label>
    <label><input type="checkbox" name="privacy" checked> Applica emoji sui volti</label>
    <button type="submit">Carica e Elabora</button>
  </form>
</body>
</html>
