<?php
// Recupera l'audio selezionato dal form
$selectedAudio = isset($_POST['audio']) ? trim($_POST['audio']) : '';
$startTime = isset($_POST['start_time']) ? floatval($_POST['start_time']) : 0;
$endTime = isset($_POST['end_time']) ? floatval($_POST['end_time']) : 0;
$volume = isset($_POST['volume']) ? floatval($_POST['volume']) : 1.0;

$processedAudioPath = '';
$audioAvailable = false;

// Se è stato selezionato un file audio valido
if ($selectedAudio !== '') {
    $audioPath = __DIR__ . '/musica/' . basename($selectedAudio);

    if (file_exists($audioPath)) {
        $audioAvailable = true;
        $tempAudioName = uniqid('audio_') . '.mp3';
        $processedAudioPath = __DIR__ . '/temp/' . $tempAudioName;

        // Costruzione del comando FFmpeg per taglio e volume
        $filters = [];
        if ($volume !== 1.0) {
            $filters[] = "volume={$volume}";
        }

        $filterOption = $filters ? '-af "' . implode(',', $filters) . '"' : '';
        $startOption = $startTime > 0 ? "-ss $startTime" : '';
        $endOption = ($endTime > 0 && $endTime > $startTime) ? "-to $endTime" : '';

        $cmd = "ffmpeg -y $startOption -i " . escapeshellarg($audioPath) . " $endOption $filterOption -c:a aac " . escapeshellarg($processedAudioPath);
        exec($cmd);
    }
}

// Dopo che hai creato $outputFinal (il video finale senza audio),
// qui sotto aggiungi l'audio selezionato

if ($audioAvailable && isset($outputFinal) && file_exists($outputFinal)) {
    $finalOutputWithAudio = str_replace('.mp4', '_with_audio.mp4', $outputFinal);

    $cmdMix = "ffmpeg -y -i " . escapeshellarg($outputFinal) .
              " -i " . escapeshellarg($processedAudioPath) .
              " -shortest -c:v copy -c:a aac " . escapeshellarg($finalOutputWithAudio);

    exec($cmdMix);

    // Sostituisce l'output originale con quello con audio
    if (file_exists($finalOutputWithAudio)) {
        unlink($outputFinal);
        rename($finalOutputWithAudio, $outputFinal);
    }

    // Pulizia audio temporaneo
    if (file_exists($processedAudioPath)) {
        unlink($processedAudioPath);
    }
}
?>
