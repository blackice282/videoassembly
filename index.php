<?php
function extractActiveScenes($inputFile, $outputDir, $prefix) {
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Tempi fittizi (potrebbero essere dinamici)
    $clipTimes = [5, 15, 25]; // secondi di inizio
    $clips = [];

    foreach ($clipTimes as $i => $start) {
        $outClip = "$outputDir/{$prefix}_clip_$i.mp4";
        $cmd = "ffmpeg -ss $start -i \"$inputFile\" -t 5 -c copy \"$outClip\"";
        shell_exec($cmd);
        if (file_exists($outClip)) {
            $clips[] = $outClip;
        }
    }

    return $clips;
}

function concatenateClipsWithList($clips, $outputFile) {
    $listFile = "clips/cliplist.txt";
    file_put_contents($listFile, '');
    foreach ($clips as $clip) {
        file_put_contents($listFile, "file '$clip'\n", FILE_APPEND);
    }

    $cmd = "ffmpeg -f concat -safe 0 -i $listFile -c copy \"$outputFile\"";
    shell_exec($cmd);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    $total_files = count($_FILES['files']['name']);
    $allClips = [];

    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['files']['tmp_name'][$i];
            $name = basename($_FILES['files']['name'][$i]);
            $destination = 'uploads/' . $name;

            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }

            if (move_uploaded_file($tmp_name, $destination)) {
                echo "✅ File caricato: $name<br>";

                // Estrai clip attive da ogni video
                $clips = extractActiveScenes($destination, 'clips', pathinfo($name, PATHINFO_FILENAME));
                $allClips = array_merge($allClips, $clips);
            }
        }
    }

    if (count($allClips) > 0) {
        if (!file_exists('clips')) {
            mkdir('clips', 0777, true);
        }

        $final = 'uploads/final_video.mp4';
        concatenateClipsWithList($allClips, $final);
        echo "<br>🎉 <strong>Montaggio completato!</strong> <a href='$final' download>Scarica il video montato</a>";
    } else {
        echo "<br>⚠️ Nessuna clip attiva trovata nei video caricati.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Automatico</title>
</head>
<body>
    <h1>Carica i tuoi video</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="files[]" multiple required>
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
