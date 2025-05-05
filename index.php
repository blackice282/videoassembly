<?php
// index.php

// Imposto le directory di upload e output
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('PROCESSED_DIR', __DIR__ . '/processed');

// Se è un POST, elaboro i file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Controllo che ci sia almeno un video caricato
    if (empty($_FILES['videos']) || $_FILES['videos']['error'][0] !== UPLOAD_ERR_OK) {
        $error = 'Nessun file video caricato.';
    } else {
        // Leggo la durata desiderata (in minuti)
        $duration = isset($_POST['duration']) ? max(1, intval($_POST['duration'])) : 3;

        // Creo le cartelle se non esistono
        @mkdir(UPLOAD_DIR, 0777, true);
        @mkdir(PROCESSED_DIR, 0777, true);

        $uploaded = $_FILES['videos'];
        $processedFiles = [];

        // Ciclo su ogni file caricato
        foreach ($uploaded['tmp_name'] as $i => $tmpName) {
            if ($uploaded['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = basename($uploaded['name'][$i]);
                $uniqName = uniqid() . '_' . $originalName;
                $destPath = UPLOAD_DIR . '/' . $uniqName;
                move_uploaded_file($tmpName, $destPath);

                // Montaggio: taglio alla durata richiesta
                $outName = 'processed_' . $uniqName;
                $outPath = PROCESSED_DIR . '/' . $outName;
                $cmd = sprintf(
                    'ffmpeg -y -i %s -t %d -c copy %s 2>&1',
                    escapeshellarg($destPath),
                    $duration * 60,
                    escapeshellarg($outPath)
                );
                shell_exec($cmd);

                if (file_exists($outPath)) {
                    $processedFiles[] = $outName;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Montaggio Video Automatico</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1>Montaggio Video Automatico</h1>

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label>Seleziona uno o più video:</label><br>
      <input type="file" name="videos[]" multiple accept="video/*"><br><br>

      <label>Durata desiderata (minuti):</label><br>
      <input type="number" name="duration" value="<?= isset($duration) ? $duration : 3 ?>" min="1"><br><br>

      <button type="submit">Carica e Monta</button>
    </form>

    <?php if (!empty($processedFiles)): ?>
      <h2>Video montati:</h2>
      <ul>
        <?php foreach ($processedFiles as $fname): ?>
          <li>
            <a href="processed/<?= rawurlencode($fname) ?>" download>
              <?= htmlspecialchars($fname) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</body>
</html>
