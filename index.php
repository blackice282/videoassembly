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
    <div class="container"><?php
// index.php - Form per caricare video
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Video</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
        }
        input[type="file"] {
            display: block;
            margin: 20px auto;
        }
        button {
            display: block;
            margin: auto;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .message {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Carica un Video</h1>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="file" name="video" accept="video/*" required>
            <button type="submit">Upload</button>
        </form>
        <?php if (isset($_GET['success'])): ?>
            <div class="message" style="color: green;">
                Video caricato con successo!
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="message" style="color: red;">
                Errore: <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// index.php - Form per caricare video
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Video</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
        }
        input[type="file"] {
            display: block;
            margin: 20px auto;
        }
        button {
            display: block;
            margin: auto;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .message {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Carica un Video</h1>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="file" name="video" accept="video/*" required>
            <button type="submit">Upload</button>
        </form>
        <?php if (isset($_GET['success'])): ?>
            <div class="message" style="color: green;">
                Video caricato con successo!
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="message" style="color: red;">
                Errore: <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

        <h1>🎬 Montaggio Video REDIVIVI</h1>
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
