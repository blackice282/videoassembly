<?php
// index.php

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['videos'])) {
        die('Nessun file video caricato.');
    }

    $durationMin = (int)$_POST['duration'];
    if ($durationMin <= 0) {
        die('Durata non valida.');
    }

    // prepara upload
    $uploadedFiles = $_FILES['videos'];
    $tmpFiles = [];
    for ($i = 0; $i < count($uploadedFiles['tmp_name']); $i++) {
        $tmp = $uploadedFiles['tmp_name'][$i];
        $name = basename($uploadedFiles['name'][$i]);
        $dest = UPLOAD_DIR . '/' . uniqid() . '_' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            die("Errore spostamento file $name.");
        }
        $tmpFiles[] = $dest;
    }

    // chiama il trim/merge
    require_once 'duration_editor.php';
    $out = duration_trim_and_merge($tmpFiles, $durationMin * 60, OUTPUT_DIR);
    if (!$out) {
        die('Errore durante il montaggio.');
    }

    // serve il file finale
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="montaggio.mp4"');
    readfile($out);
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Montaggio Video Automatico</title>
</head>
<body>
  <h1>Montaggio Video Automatico</h1>
  <form method="post" enctype="multipart/form-data">
    <label>Seleziona video (più file):<br>
      <input type="file" name="videos[]" multiple accept="video/*" required>
    </label><br><br>
    <label>Durata desiderata (minuti):<br>
      <input type="number" name="duration" value="3" min="1" required>
    </label><br><br>
    <button type="submit">Carica e Monta</button>
  </form>
</body>
</html>
