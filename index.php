<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';

function createUploadsDir() {
    $uploadPath = getConfig('paths.uploads', 'uploads');
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0777, true);
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
            $tmp = getConfig('paths.temp', 'temp') . '/effect_' . basename($working);
            if (applyVideoEffect($working, $tmp, $options['effect'])) {
                $working = $tmp;
            }
        }
        if ($options['privacy']) {
            $tmp = getConfig('paths.temp', 'temp') . '/privacy_' . basename($working);
            if (applyFacePrivacy($working, $tmp)) {
                $working = $tmp;
            }
        }
        $processedVideos[] = $working;
    }
    $outputFinal = getConfig('paths.uploads', 'uploads') . '/' . generateOutputName();
    applyTransitions($processedVideos, $outputFinal);
    if ($options['audio'] !== 'none') {
        $audio = getRandomAudioFromCategory($options['audio']);
        if ($audio && downloadAudio($audio['url'], $tmpAudio := getConfig('paths.temp', 'temp') . '/track.mp3')) {
            applyBackgroundAudio($outputFinal, $tmpAudio, $outputFinal, 0.3);
        }
    }
    return $outputFinal;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['videos'])) {
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

    $uploaded = $_FILES['videos'];
    $count = count($uploaded['name']);
    $videos = [];

    for ($i = 0; $i < $count; $i++) {
        if ($uploaded['error'][$i] === UPLOAD_ERR_OK) {
            $tmp = $uploaded['tmp_name'][$i];
            $name = basename($uploaded['name'][$i]);
            $dest = getConfig('paths.uploads', 'uploads') . '/' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $videos[] = $dest;
            }
        }
    }

    if (count($videos) > 0) {
        $final = processVideoChain($videos, $options);
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="'.basename($final).'"');
        readfile($final);
        exit;
    } else {
        echo "Nessun video caricato correttamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎬 Montaggio Video Automatico</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; }
        .upload-container { border: 2px dashed #ccc; padding: 20px; text-align: center; border-radius: 5px; }
        input[type="file"], input[type="number"], button { margin: 10px 0; padding: 10px; width: 100%; max-width: 400px; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>🎬 Montaggio Video Automatico</h1>
    <div class="upload-container">
        <form method="POST" enctype="multipart/form-data">
            <label>Seleziona i video (multipli):</label>
            <input type="file" name="videos[]" multiple accept="video/*">
            <label>Durata desiderata (minuti):</label>
            <input type="number" name="duration" value="3" min="1">
            <button type="submit">Carica e Monta</button>
        </form>
    </div>
</body>
</html>
