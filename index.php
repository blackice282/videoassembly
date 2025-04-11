<?php
// Mostra gli errori
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Definisci le cartelle per il caricamento e l'output
$temp_dir = 'uploads/' . uniqid();
$output_dir = 'results/' . uniqid();

// Verifica se le cartelle sono scrivibili
if (!is_writable($temp_dir)) {
    echo "Errore: La cartella $temp_dir non è scrivibile.";
    exit;
}

if (!is_writable($output_dir)) {
    echo "Errore: La cartella $output_dir non è scrivibile.";
    exit;
}

// Verifica se il form è stato inviato
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    // Crea la cartella temporanea se non esiste
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    
    // Crea la cartella per i risultati se non esiste
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0777, true);
    }
    
    // Carica i file
    foreach ($_FILES['files']['tmp_name'] as $index => $tmp_name) {
        $file_path = $temp_dir . '/' . $_FILES['files']['name'][$index];
        if (move_uploaded_file($tmp_name, $file_path)) {
            echo "File caricato con successo: " . $_FILES['files']['name'][$index] . "<br>";
        } else {
            echo "Errore durante il caricamento del file: " . $_FILES['files']['name'][$index] . "<br>";
            continue;  // Salta l'elaborazione di questo file
        }

        // Verifica che il file esista
        if (!file_exists($file_path)) {
            echo "Errore: Il file video non esiste: " . $file_path;
            continue;
        }

        // Esegui il comando FFmpeg per elaborare il video (ad esempio, per ridimensionarlo)
        $cmd = "ffmpeg -i " . escapeshellarg($file_path) . " -vf scale=720:-1 " . escapeshellarg($output_dir . '/output_video.mp4');
        exec($cmd, $output, $return_code);

        // Verifica se FFmpeg ha avuto un errore
        if ($return_code !== 0) {
            echo "Errore durante l'esecuzione di FFmpeg: " . implode("\n", $output);
            continue;
        }
    }
    
    // Verifica che il video finale esista
    $final_video_path = $output_dir . '/output_video.mp4';
    if (file_exists($final_video_path)) {
        echo "Video finale creato con successo: " . $final_video_path;
    } else {
        echo "Errore: Il video finale non è stato creato.";
    }
}
?>

<!-- Form per caricare i file -->
<form action="index.php" method="post" enctype="multipart/form-data">
    Seleziona i video da caricare:
    <input type="file" name="files[]" multiple>
    <input type="submit" value="Carica video">
</form>
