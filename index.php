<?php
require_once __DIR__ . '/config.php';

// Percorsi
$uploadDir = __DIR__ . '/' . getConfig('paths.uploads');
$musicDir = __DIR__ . '/music/';

// Crea cartelle se non esistono
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($musicDir)) mkdir($musicDir, 0777, true);

// Lista tracce musicali disponibili
$musicFiles = [];
foreach (is_dir($musicDir) ? scandir($musicDir) : [] as $file) {
    if (preg_match('/\.(mp3|wav|ogg)$/i', $file)) {
        $musicFiles[] = $file;
    }
}

// Gestione upload e montaggio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['videos'])) {
    $uploaded = [];
    foreach ($_FILES['videos']['tmp_name'] as $i => $tmpName) {
        $name = basename($_FILES['videos']['name'][$i]);
        $dest = $uploadDir . '/' . $name;
        if (move_uploaded_file($tmpName, $dest)) {
            $uploaded[] = $dest;
        }
    }
    if ($uploaded) {
        // Crea file di testo per FFmpeg
        $listFile = $uploadDir . '/list.txt';
        $handle = fopen($listFile, 'w');
        foreach ($uploaded as $file) {
            fwrite($handle, "file '" . str_replace("'", "'\\''", $file) . "'\n");
        }
        fclose($handle);

        // Output finale
        $outputFile = $uploadDir . '/output.mp4';

        // Montaggio semplice (concatena)
        $cmd = "ffmpeg -y -f concat -safe 0 -i \"$listFile\" -c copy \"$outputFile\"";
        exec($cmd, $out, $ret);
        echo "<pre>CMD: $cmd\n";
        print_r($out);
        echo "\nRET: $ret</pre>";

        // Aggiunta musica di sottofondo se selezionata
        $selectedMusic = isset($_POST['music']) ? $_POST['music'] : '';
        if ($selectedMusic && file_exists($musicDir . $selectedMusic)) {
            $outputWithMusic = $uploadDir . '/output_music.mp4';
            $cmdMusic = "ffmpeg -y -i \"$outputFile\" -i \"{$musicDir}{$selectedMusic}\" -c:v copy -c:a aac -shortest \"$outputWithMusic\"";
            exec($cmdMusic, $outMusic, $retMusic);
            echo "<pre>CMD: $cmdMusic\n";
            print_r($outMusic);
            echo "\nRET: $retMusic</pre>";
            if ($retMusic === 0 && file_exists($outputWithMusic)) {
                unlink($outputFile);
                rename($outputWithMusic, $outputFile);
            }
        }

        if (file_exists($outputFile)) {
            echo "<p>Video montato con successo! <a href='" . getConfig('paths.uploads') . "/output.mp4' download>Scarica il video finale</a></p>";
        } else {
            echo "<p>Errore nel montaggio video.</p>";
        }
    } else {
        echo "<p>Nessun file caricato.</p>";
    }
}
?>

<h1>VideoAssembly - Upload Video</h1>
<form method="post" enctype="multipart/form-data">
    <label>Seleziona uno o più video MP4 da caricare:</label><br>
    <input type="file" name="videos[]" accept="video/mp4" multiple required><br><br>
    <label>Scegli la musica di sottofondo:</label><br>
    <select name="music" id="music-select" onchange="updateAudioPlayer()">
        <option value="">-- Nessuna musica --</option>
        <?php foreach ($musicFiles as $music): ?>
            <option value="<?php echo htmlspecialchars($music); ?>"><?php echo htmlspecialchars($music); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="button" onclick="playSelectedMusic()">Ascolta</button>
    <audio id="audio-player" controls style="display:none;"></audio>
    <br><br>
    <button type="submit">Carica e Monta Video</button>
</form>
<script>
function updateAudioPlayer() {
    var select = document.getElementById('music-select');
    var audio = document.getElementById('audio-player');
    if (select.value) {
        audio.src = 'music/' + select.value;
        audio.style.display = 'inline';
    } else {
        audio.src = '';
        audio.style.display = 'none';
    }
}
function playSelectedMusic() {
    var audio = document.getElementById('audio-player');
    if (audio.src) {
        audio.play();
    }
}
</script>