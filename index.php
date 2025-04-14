<?php
function createUploadsDir() {
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }
}

function convertToTs($inputFile, $outputTs) {
    $cmd = "ffmpeg -i \"$inputFile\" -c copy -bsf:v h264_mp4toannexb -f mpegts \"$outputTs\"";
    shell_exec($cmd);
}

function concatenateTsFiles($tsFiles, $outputFile) {
    $tsList = implode('|', $tsFiles);
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$outputFile\"";
    shell_exec($cmd);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    createUploadsDir();

    $uploaded_ts_files = [];

    $total_files = count($_FILES['files']['name']);
    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['files']['tmp_name'][$i];
            $name = basename($_FILES['files']['name'][$i]);
            $destination = 'uploads/' . $name;

            if (move_uploaded_file($tmp_name, $destination)) {
                echo "✅ File caricato: $name<br>";

                // Converti in .ts per la concatenazione
                $tsFile = 'uploads/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                convertToTs($destination, $tsFile);
                $uploaded_ts_files[] = $tsFile;
            } else {
                echo "❌ Errore nel salvataggio del file: $name<br>";
            }
        }
    }

    if (count($uploaded_ts_files) > 1) {
        $outputFinal = 'uploads/final_video.mp4';
        concatenateTsFiles($uploaded_ts_files, $outputFinal);
        echo "<br>🎉 <strong>Montaggio completato!</strong> <a href='$outputFinal' download>Clicca qui per scaricare il video</a>";
    } else {
        echo "<br>⚠️ Carica almeno due video per generare un montaggio.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carica e Monta Video</title>
</head>
<body>
    <h1>🎬 Carica i tuoi video per il montaggio</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="files[]" multiple accept="video/mp4">
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
