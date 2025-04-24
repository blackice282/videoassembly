<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';

function createUploadsDir() {
    if (!file_exists(getConfig('paths.uploads', 'uploads'))) {
        mkdir(getConfig('paths.uploads', 'uploads'), 0777, true);
    }
    if (!file_exists(getConfig('paths.temp', 'temp'))) {
        mkdir(getConfig('paths.temp', 'temp'), 0777, true);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["videos"])) {
    createUploadsDir();
    set_time_limit(600);

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = isset($_POST['duration']) ? intval($_POST['duration']) * 60 : 0;
    $durationMethod = $_POST['duration_method'] ?? 'trim';
    setConfig('duration_editor.method', $durationMethod);

    $effect = $_POST['effect'] ?? 'none';
    $audio = $_POST['audio'] ?? 'none';
    $privacy = isset($_POST['privacy']);

    $uploaded = $_FILES["videos"];
    $uploadedCount = count($uploaded["name"]);
    $tempVideos = [];

    for ($i = 0; $i < $uploadedCount; $i++) {
        if ($uploaded["error"][$i] === UPLOAD_ERR_OK) {
            $name = basename($uploaded["name"][$i]);
            $tmpPath = $uploaded["tmp_name"][$i];
            $destPath = getConfig('paths.uploads', 'uploads') . '/' . uniqid("video_") . "_" . $name;
            move_uploaded_file($tmpPath, $destPath);
            $tempVideos[] = $destPath;
        }
    }

    echo "<h2>Video caricati:</h2><ul>";
    foreach ($tempVideos as $video) {
        echo "<li>$video</li>";
    }
    echo "</ul><p><strong>Elaborazione non ancora implementata.</strong></p>";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 2rem;
            background: #f4f4f4;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,.1);
            max-width: 600px;
        }
        label {
            display: block;
            margin: 1rem 0 0.5rem;
        }
        input, select {
            width: 100%;
            padding: 0.5rem;
        }
        button {
            margin-top: 1rem;
            padding: 0.7rem 1.2rem;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <h1>VideoAssembly – Upload multipli</h1>
    <form method="post" enctype="multipart/form-data">
        <label>Carica uno o più video:
            <input type="file" name="videos[]" accept="video/*" multiple required>
        </label>
        <label>Modalità:
            <select name="mode">
                <option value="simple">Semplice</option>
                <option value="detect_people">Rileva persone</option>
            </select>
        </label>
        <label>Durata massima (in minuti):
            <input type="number" name="duration" value="3">
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
                <option value="bw">Bianco e nero</option>
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
        <label><input type="checkbox" name="privacy" checked> Applica emoji privacy (volti)</label>
        <button type="submit">Carica e elabora</button>
    </form>
</body>
</html>
