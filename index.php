<?php
function extractActiveClips($input, $output, $clipDuration = 2, $numClips = 2) {
    $sceneDir = 'uploads/scenes';
    if (!file_exists($sceneDir)) {
        mkdir($sceneDir, 0777, true);
    }

    $sceneFile = "$sceneDir/scene_" . uniqid() . ".txt";

    // Rileva le scene con ffmpeg
    $cmd = "ffmpeg -i \"$input\" -filter_complex \"select='gt(scene,0.3)',showinfo\" -f null - 2>&1";
    $outputCmd = shell_exec($cmd);

    preg_match_all('/pts_time:([0-9.]+)/', $outputCmd, $matches);
    $timestamps = $matches[1] ?? [];

    $clips = [];
    for ($i = 0; $i < min(count($timestamps), $numClips); $i++) {
        $start = $timestamps[$i];
        $clips[] = [
            'start' => $start,
            'duration' => $clipDuration
        ];
    }

    // Estrai i clip e salva in un file temporaneo
    $clipFiles = [];
    foreach ($clips as $index => $clip) {
        $clipPath = "$sceneDir/clip_" . uniqid() . "_$index.mp4";
        $cmd = "ffmpeg -ss {$clip['start']} -i \"$input\" -t {$clip['duration']} -c copy \"$clipPath\"";
        shell_exec($cmd);
        $clipFiles[] = $clipPath;
    }

    // Salva il file per concatenazione
    $listFile = "$sceneDir/list_" . uniqid() . ".txt";
    file_put_contents($listFile, implode("\n", array_map(fn($f) => "file '$f'", $clipFiles)));

    // Concatena i clip
    $cmd = "ffmpeg -f concat -safe 0 -i $listFile -c copy \"$output\"";
    shell_exec($cmd);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    $total_files = count($_FILES['files']['name']);

    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    $intermediateClips = [];

    for ($i = 0; $i < $total_files; $i++) {
        $tmp_name = $_FILES['files']['tmp_name'][$i];
        $name = basename($_FILES['files']['name'][$i]);
        $uploadPath = 'uploads/' . $name;

        if (move_uploaded_file($tmp_name, $uploadPath)) {
            echo "✅ Caricato: $name<br>";

            // Estrai le scene attive da ciascun video
            $partialClip = 'uploads/clip_' . uniqid() . '.mp4';
            extractActiveClips($uploadPath, $partialClip);
            $intermediateClips[] = $partialClip;
        } else {
            echo "❌ Errore nel caricamento di $name<br>";
        }
    }

    if (count($intermediateClips) > 0) {
        $concatList = 'uploads/final_list.txt';
        file_put_contents($concatList, implode("\n", array_map(fn($f) => "file '$f'", $intermediateClips)));

        $finalVideo = 'uploads/final_video.mp4';
        $cmd = "ffmpeg -f concat -safe 0 -i $concatList -c:v libx264 -preset fast -crf 23 -c:a aac $finalVideo";
        shell_exec($cmd);

        if (file_exists($finalVideo)) {
            echo "<br><strong>🎬 Video montato!</strong> <a href='download.php'>Scarica il video montato</a>";
        } else {
            echo "❌ Errore nella generazione del video finale.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video</title>
</head>
<body>
    <h1>Carica i tuoi video</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="files[]" multiple required>
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
