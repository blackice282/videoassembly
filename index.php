<?php
// index.php - Pagina principale per il caricamento video e parametri
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Montaggio Video Verticale 9:16</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Montaggio Video REDIVIVI</h1>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="video">Seleziona video:</label>
                <input type="file" name="video[]" id="video" accept="video/*" multiple required>
            </div>
            <div class="form-group">
                <label for="duration">Durata desiderata (minuti):</label>
                <input type="number" name="duration" id="duration" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label for="ai_instructions">Istruzioni AI:</label>
                <textarea name="ai_instructions" id="ai_instructions" placeholder="Descrivi l'intervento desiderato"></textarea>
            </div>
            <div class="form-group">
                <button type="submit">Carica e Monta</button>
            </div>
        </form>
    </div>
</body>
</html>
