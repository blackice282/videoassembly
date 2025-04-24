<?php
require_once 'config.php';
require_once 'video_processor.php';
require_once 'debug_utility.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    $file = $_FILES['video']['tmp_name'];
    $originalName = basename($_FILES['video']['name']);
    $destPath = UPLOAD_DIR . $originalName;

    if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
    move_uploaded_file($file, $destPath);

    $options = [
        'apply_face_privacy' => isset($_POST['privacy']),
        'people_detection' => isset($_POST['people']),
        'max_duration' => intval($_POST['max_duration'] ?? MAX_DURATION),
        'apply_effect' => $_POST['effect'] !== 'none',
        'effect_name' => $_POST['effect'],
        'apply_audio' => $_POST['audio'] !== 'none',
        'audio_category' => $_POST['audio'],
        'audio_volume' => 0.3,
    ];

    $result = process_uploaded_video($destPath, $options);

    echo "<h2>Video Elaborato</h2>";
    echo "<p><a href='{$result['output_file']}'>Scarica il video</a></p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 2rem;
            background-color: #f7f7f7;
        }
        form {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            max-width: 600px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        label {
            display: block;
            margin: 1rem 0 0.5rem;
        }
        button {
            margin-top: 1rem;
            padding: 0.5rem 1.5rem;
            background: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <h1>VideoAssembly</h1>
    <form method="post" enctype="multipart/form-data">
        <label>Carica un video:
            <input type="file" name="video" required>
        </label>
        <label><input type="checkbox" name="privacy" checked> Offusca i volti con emoji</label>
        <label><input type="checkbox" name="people"> Rileva presenza persone</label>

        <label>Durata massima del video (secondi):
            <input type="number" name="max_duration" value="180">
        </label>

        <label>Effetto video:
            <select name="effect">
                <option value="none">Nessuno</option>
                <option value="bw">Bianco e nero</option>
                <option value="vintage">Vintage</option>
                <option value="contrast">Contrasto forte</option>
            </select>
        </label>

        <label>Audio di sottofondo:
            <select name="audio">
                <option value="none">Nessuno</option>
                <option value="emozionale">Emozionale</option>
            </select>
        </label>

        <button type="submit">Elabora Video</button>
    </form>
</body>
</html>
