<?php
require_once 'config.php';

function createDirs() {
    foreach ([UPLOAD_DIR, PROCESSED_DIR] as $d) {
        if (!is_dir($d)) mkdir($d, 0777, true);
    }
}

createDirs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['videos'])) {
    $duration = (int)$_POST['duration_minutes'];
    $aiInstruction = trim($_POST['ai_instruction'] ?? '');

    foreach ($_FILES['videos']['tmp_name'] as $idx => $tmpPath) {
        if ($_FILES['videos']['error'][$idx] !== UPLOAD_ERR_OK) continue;

        $ext = pathinfo($_FILES['videos']['name'][$idx], PATHINFO_EXTENSION);
        $id  = uniqid();
        $in  = UPLOAD_DIR . "/{$id}.{$ext}";
        $out = PROCESSED_DIR . "/{$id}_out.mp4";

        move_uploaded_file($tmpPath, $in);

        // qui passiamo anche $aiInstruction al processing, se serve
        processVideoVertical($in, $out, $duration, $aiInstruction);

        echo "<p>Video montato: <a href=\"download.php?f=" . basename($out) . "\">" . basename($out) . "</a></p>";
    }
    exit;
}

function processVideoVertical($input, $output, $duration, $aiInstruction) {
    // filtro 9:16: scala l’altezza a 1280px e crop di 720×1280 (centro)
    $filter = "scale=-2:1280,crop=720:1280";
    // qui potresti passare $aiInstruction ad un tuo servizio AI
    $cmd = sprintf(
        'ffmpeg -y -i %s -t %d -vf "%s" -c:v libx264 -preset fast -c:a copy %s 2>&1',
        escapeshellarg($input),
        $duration * 60,
        $filter,
        escapeshellarg($output)
    );
    exec($cmd, $out, $ret);
    return $ret === 0;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Montaggio Video Automatico</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>Montaggio Video Automatico</h1>
  <form action="" method="post" enctype="multipart/form-data">
    <label>Seleziona video (più file)</label><br>
    <input type="file" name="videos[]" multiple accept="video/*"><br><br>

    <label>Durata desiderata (minuti)</label><br>
    <input type="number" name="duration_minutes" value="3" min="1"><br><br>

    <label>Istruzioni AI</label><br>
    <textarea name="ai_instruction" rows="3" placeholder="Es. ‘Applica slow-motion nei primi 10 secondi’"></textarea><br><br>

    <button type="submit">Carica e Monta</button>
  </form>
</body>
</html>
