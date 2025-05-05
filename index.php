<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$config = include __DIR__ . '/config.php';

$uploadDir = $config['UPLOAD_DIR'];
$processedDir = $config['PROCESSED_DIR'];
$maxFileSize = $config['MAX_FILE_SIZE'];
$allowedExts = $config['ALLOWED_EXTENSIONS'];

function createDirs($dirs) {
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                die("Failed to create directory: $dir");
            }
        }
    }
}

createDirs([$uploadDir, $processedDir]);

$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['videos'])) {
        $messages[] = ['type' => 'error', 'text' => 'Nessun file video caricato.'];
    } else {
        $duration = intval($_POST['duration'] ?? 0);
        if ($duration <= 0) {
            $messages[] = ['type' => 'error', 'text' => 'Durata non valida.'];
        } else {
            foreach ($_FILES['videos']['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['videos']['tmp_name'][$key];
                    $name = basename($_FILES['videos']['name'][$key]);
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExts)) {
                        $messages[] = ['type' => 'error', 'text' => "Formato non consentito: $name"];
                        continue;
                    }
                    if ($_FILES['videos']['size'][$key] > $maxFileSize) {
                        $messages[] = ['type' => 'error', 'text' => "File troppo grande: $name"];
                        continue;
                    }
                    $dest = $uploadDir . '/' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmpName, $dest)) {
                        // Logica di montaggio da integrare qui
                        $outputVideo = $processedDir . '/' . uniqid() . '.mp4';
                        copy($dest, $outputVideo);
                        $messages[] = ['type' => 'success', 'text' => "Video processato: <a href="$outputVideo">Scarica</a>"];
                    } else {
                        $messages[] = ['type' => 'error', 'text' => "Errore nel caricamento: $name"];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Montaggio Video Automatico</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<h1>Montaggio Video Automatico</h1>
<?php foreach ($messages as $msg): ?>
    <div class="msg <?= $msg['type']; ?>"><?= $msg['text']; ?></div>
<?php endforeach; ?>
<form method="POST" enctype="multipart/form-data">
    <label for="videos">Seleziona video:</label>
    <input type="file" name="videos[]" id="videos" multiple accept="video/*">
    <label for="duration">Durata desiderata (minuti):</label>
    <input type="number" name="duration" id="duration" value="3" min="1">
    <button type="submit">Carica e Monta</button>
</form>
</div>
</body>
</html>
