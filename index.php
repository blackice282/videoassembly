<?php
// index.php - Montaggio Video Automatico
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('OUTPUT_DIR', __DIR__ . '/processed');

// Creazione delle cartelle, se non esistono
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
if (!is_dir(OUTPUT_DIR)) mkdir(OUTPUT_DIR, 0777, true);

// Gestione invio form
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 3;
    if (isset($_FILES['videos']) && count($_FILES['videos']['name']) > 0 && !empty($_FILES['videos']['name'][0])) {
        $uploadedFiles = [];
        foreach ($_FILES['videos']['tmp_name'] as $index => $tmpName) {
            $originalName = $_FILES['videos']['name'][$index];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $newName = uniqid('video_', true) . '.' . $extension;
            $destination = UPLOAD_DIR . '/' . $newName;
            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedFiles[] = $destination;
            }
        }
        // TODO: integrare qui la logica di montaggio video con FFmpeg o altra libreria
        // Per ora restituiamo il primo video caricato come esempio
        $outputFile = $uploadedFiles[0];

        // Invio del file per il download
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . basename($outputFile) . '"');
        readfile($outputFile);
        exit;
    } else {
        $errorMsg = 'Nessun file video caricato.';
    }
}
?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Automatico</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; }
        label { display: block; margin-top: 15px; color: #555; }
        input[type="file"] { display: block; margin-top: 5px; }
        input[type="number"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 16px; }
        button:hover { background: #218838; cursor: pointer; }
        .error { margin-top: 20px; color: red; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <h1>Montaggio Video Automatico</h1>
    <?php if (!empty($errorMsg)): ?>
        <div class="error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label for="videos">Seleziona video:</label>
        <input type="file" id="videos" name="videos[]" multiple accept="video/*">
        <label for="duration">Durata desiderata (minuti):</label>
        <input type="number" id="duration" name="duration" min="1" value="<?= isset($duration) ? htmlspecialchars($duration) : '3' ?>">
        <button type="submit">Carica e Monta</button>
    </form>
</div>
</body>
</html>
