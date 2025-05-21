<?php
$audioFile = $_POST['audio'] ?? null;
$start = $_POST['start_time'] ?? 0;
$end = $_POST['end_time'] ?? 0;
$volume = $_POST['volume'] ?? 1;

if (!$audioFile) {
    die("❌ Nessun file audio selezionato.");
}

$audioPath = __DIR__ . '/musica/' . basename($audioFile);
if (!file_exists($audioPath)) {
    die("❌ File audio non trovato.");
}

$outputDir = __DIR__ . '/output';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// 1. Taglia l’audio (se end > start)
$cutAudio = $audioPath;
$cutCmd = '';
if ($end > 0 && $end > $start) {
    $cutAudio = "$outputDir/cut_" . uniqid() . ".mp3";
    $cutCmd = "ffmpeg -y -i \"$audioPath\" -ss $start -to $end -c copy \"$cutAudio\"";
    exec($cutCmd);
}

// 2. Regola il volume (se diverso da 1.0)
$finalAudio = $cutAudio;
if ($volume != 1) {
    $finalAudio = "$outputDir/vol_" . uniqid() . ".mp3";
    $volCmd = "ffmpeg -y -i \"$cutAudio\" -filter:a \"volume=$volume\" \"$finalAudio\"";
    exec($volCmd);
}

// 3. Montaggio con video
$inputVideo = __DIR__ . '/output_video.mp4'; // da aggiornare se necessario
$outputFinal = $outputDir . '/video_finale_' . uniqid() . '.mp4';

$cmd = "ffmpeg -y -i \"$inputVideo\" -i \"$finalAudio\" -shortest -c:v copy -c:a aac \"$outputFinal\"";
exec($cmd, $out, $res);

if ($res === 0) {
    echo "✅ Video creato con successo: <a href='output/" . basename($outputFinal) . "' target='_blank'>" . basename($outputFinal) . "</a>";
} else {
    echo "❌ Errore nel montaggio. Codice: $res";
}
?>
