<?php
require 'config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Verticale 9:16</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Montaggio Video Verticale 9:16</h1>
    <form action="status.php" method="post" enctype="multipart/form-data">
        <label for="videos">Seleziona video:</label>
        <input type="file" name="videos[]" id="videos" multiple accept="video/*">
        <label for="duration">Durata desiderata (minuti):</label>
        <input type="number" name="duration" id="duration" value="3" min="1">
        <label for="ai_instructions">Istruzioni AI:</label>
        <textarea name="ai_instructions" id="ai_instructions" rows="3" placeholder="Descrivi l'intervento desiderato"></textarea>
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
?>