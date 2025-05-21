<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';

function createUploadsDir() {
    if (!file_exists(getConfig('paths.uploads', 'uploads'))) {
        mkdir(getConfig('paths.uploads', 'uploads'), 0777, true);
    }
    if (!file_exists(getConfig('paths.temp', 'temp'))) {
        mkdir(getConfig('paths.temp', 'temp'), 0777, true);
    }
}

function convertToTs($inputFile, $outputTs) {
    $cmd = "ffmpeg -i \"$inputFile\" -c copy -bsf:v h264_mp4toannexb -f mpegts \"$outputTs\"";
    shell_exec($cmd);
}

function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null) {
    $tsList = implode('|', $tsFiles);
    $tempMerged = "temp/merged_" . uniqid() . ".mp4";
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$tempMerged\"";
    shell_exec($cmd);

    // Applica musica se richiesta
    if ($audioPath && file_exists($audioPath)) {
        $final = process_video($tempMerged, $audioPath);
        if ($final['success']) {
            copy(parse_url($final['video_url'], PHP_URL_PATH), $outputFile);
        }
    } else {
        copy($tempMerged, $outputFile);
    }

    unlink($tempMerged);
}

function cleanupTempFiles($files, $keepOriginals = false) {
    foreach ($files as $file) {
        if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
            unlink($file);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    createUploadsDir();
    set_time_limit(300);

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = (isset($_POST['duration']) && is_numeric($_POST['duration'])) ? intval($_POST['duration']) * 60 : 0;
    $durationMethod = $_POST['duration_method'] ?? 'trim';
    $selectedAudio = trim($_POST['background_music'] ?? '');
    $audioPath = $selectedAudio ? realpath(__DIR__ . "/musica/" . $selectedAudio) : null;

    setConfig('duration_editor.method', $durationMethod);

    // (tutto il codice upload & elaborazione rimane invariato...)

    // ✂️ Tagliato per brevità - puoi reinserire la parte di caricamento video qui sopra

    // Nell'uso reale di concatenateTsFiles, aggiungi $audioPath come terzo parametro
    // Esempio:
    // concatenateTsFiles($tsFiles, $outputFinal, $audioPath);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly - Montaggio Video</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 800px; margin: auto; }
        audio { margin-top: 10px; }
    </style>
    <script>
        function previewAudio(fileName) {
            const audioPlayer = document.getElementById('audioPreview');
            if (fileName) {
                audioPlayer.src = 'musica/' + fileName;
                audioPlayer.style.display = 'block';
                audioPlayer.load();
            } else {
                audioPlayer.src = '';
                audioPlayer.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <h1>🎬 VideoAssembly</h1>
    <form method="POST" enctype="multipart/form-data">
        <label>Carica i tuoi video:</label><br>
        <input type="file" name="files[]" multiple accept="video/*"><br><br>

        <label>Musica di sottofondo (opzionale):</label><br>
        <select name="background_music" onchange="previewAudio(this.value)">
            <option value="">-- Nessuna --</option>
            <?php
            $musicDir = __DIR__ . '/musica/';
            foreach (scandir($musicDir) as $file) {
                if (preg_match('/\.(mp3|wav)$/i', $file)) {
                    echo "<option value=\"$file\">$file</option>";
                }
            }
            ?>
        </select><br>
        <audio id="audioPreview" controls style="display:none;"></audio><br><br>

        <!-- Altri campi (mode, durata...) restano invariati -->

        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
