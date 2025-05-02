<?php
// index.php - Caricamento video e selezione durata
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Video Assembly - Montaggio Automatico</title>
</head>
<body>
  <h1>Montaggio Video Automatico</h1>
  <form enctype="multipart/form-data" action="monta.php" method="POST">
    <label for="video">Seleziona video:</label>
    <input type="file" name="video" id="video" accept="video/*" required><br><br>
    <label for="duration">Durata desiderata (minuti):</label>
    <input type="number" id="duration" name="duration" min="1" max="60" value="3" required><br><br>
    <button type="submit">Carica e Monta</button>
  </form>
</body>
</html>
