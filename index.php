<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Verticale 9:16</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>🎬 Montaggio Video Verticale 9:16</h1>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="videos">Seleziona video:</label>
                <input type="file" id="videos" name="videos[]" multiple required>
            </div>
            <div class="form-group">
                <label for="duration">Durata desiderata (minuti):</label>
                <input type="number" id="duration" name="duration" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label for="instructions">Istruzioni AI:</label>
                <textarea id="instructions" name="instructions" rows="4" placeholder="Descrivi l'intervento desiderato"></textarea>
            </div>
            <button type="submit" class="btn">🎞 Carica e Monta</button>
        </form>
    </div>
</body>
</html>
