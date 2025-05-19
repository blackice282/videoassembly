<?php
// Recupero audio di sottofondo scelto dall'utente
$backgroundFile = isset($_POST['background_file']) ? basename($_POST['background_file']) : null;
$backgroundAudio = null;
if ($backgroundFile) {
    $path = __DIR__ . '/musica/' . $backgroundFile;
    if (file_exists($path)) {
        $backgroundAudio = $path;
    }
}

// Preparazione degli input video (qui aggiungi il tuo codice esistente che popola $inputsVideo)
// Esempio: $inputsVideo = ['clip1.mp4', 'clip2.mp4'];

// Costruzione del comando FFmpeg
$cmd = [];

// Input video
foreach ($inputsVideo as $input) {
    $cmd[] = "-i {$input}";
}

// Input audio in loop (selezionato)
if ($backgroundAudio) {
    // loop infinito dell'audio di sottofondo
    $cmd[] = "-stream_loop -1 -i {$backgroundAudio}";
}

// Costruzione dei filtri
$filters = [];

// ... qui i tuoi filtri video e transizioni esistenti ...

// Mix audio: video + sottofondo
if ($backgroundAudio) {
    // 0:a = audio del video (se presente), 1:a = audio di sottofondo in loop
    $filters[] = "[0:a][1:a]amix=inputs=2:duration=first:dropout_transition=3[aout]";
    $mapAudio = "-map [aout]";
} else {
    $mapAudio = '';
}

// Mappatura video
$mapVideo = "-map 0:v";

// Unione del comando
$filterComplex = implode(';', $filters);
$fullCmd = sprintf(
    'ffmpeg %s -filter_complex "%s" %s %s -c:v libx264 -c:a aac -shortest output.mp4',
    implode(' ', $cmd),
    $filterComplex,
    $mapVideo,
    $mapAudio
);

// Esecuzione
exec($fullCmd, $output, $return_var);
if ($return_var !== 0) {
    echo "Errore durante la generazione del video.";
} else {
    echo "Video creato con successo: <a href=\"output.mp4\">Scarica qui</a>";
}
?>