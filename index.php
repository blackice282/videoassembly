<?php
$musicaDir = __DIR__ . '/musica';
$audioFiles = array_filter(scandir($musicaDir), function ($file) use ($musicaDir) {
    return in_array(pathinfo($file, PATHINFO_EXTENSION), ['mp3', 'wav', 'ogg']);
});
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly - Montaggio</title>
</head>
<body>
    <h2>🎬 Crea il tuo video</h2>

    <form method="POST" action="ffmpeg_script.php">
        <h3>🎵 Seleziona una traccia audio:</h3>
        <?php foreach ($audioFiles as $audio): ?>
            <div style="margin-bottom: 15px;">
                <input type="radio" name="audio" value="<?= htmlspecialchars($audio) ?>" required>
                <?= htmlspecialchars($audio) ?><br>
                <audio controls style="width: 300px;">
                    <source src="musica/<?= urlencode($audio) ?>" type="audio/<?= pathinfo($audio, PATHINFO_EXTENSION) ?>">
                    Il tuo browser non supporta l’audio.
                </audio>
            </div>
        <?php endforeach; ?>

        <h4>🔊 Volume (0.0 - 2.0):</h4>
        <input type="number" name="volume" step="0.1" min="0" max="2" value="1">

        <h4>✂️ Taglia audio</h4>
        <label>Inizio (secondi):</label>
        <input type="number" name="start_time" min="0" step="1" value="0"><br>
        <label>Fine (secondi):</label>
        <input type="number" name="end_time" min="0" step="1" value="0">

        <br><br>
        <button type="submit">🎬 Avvia Montaggio</button>
    </form>
</body>
</html>
