<?php
// Funzione per verificare se FFmpeg è disponibile
function is_ffmpeg_available() {
    exec('which ffmpeg 2>&1', $output, $return_code);
    return $return_code === 0;
}

// Funzione per creare una miniatura da un video
function create_thumbnail($video_path, $thumbnail_path) {
    $cmd = "ffmpeg -i " . escapeshellarg($video_path) . 
           " -ss 00:00:03 -vframes 1 " . 
           escapeshellarg($thumbnail_path) . " 2>&1";
    exec($cmd, $output, $return_code);
    return $return_code === 0;
}

// Funzione per tagliare un video
function trim_video($input_path, $output_path, $duration) {
    $cmd = "ffmpeg -i " . escapeshellarg($input_path) . 
           " -t " . intval($duration) . 
           " -c:v libx264 -c:a aac " . 
           escapeshellarg($output_path) . " 2>&1";
    exec($cmd, $output, $return_code);
    return $return_code === 0;
}

// Funzione per concatenare i video
function concatenate_videos($video_files, $output_path) {
    $list_file = "temp_list_" . uniqid() . ".txt";
    $list_content = "";

    foreach ($video_files as $file) {
        $list_content .= "file '" . str_replace("'", "'\\''", $file) . "'\n";
    }

    file_put_contents($list_file, $list_content);

    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($list_file) . 
           " -c copy " . escapeshellarg($output_path) . " 2>&1";
    exec($cmd, $output, $return_code);

    unlink($list_file);

    return $return_code === 0;
}

// Funzione per applicare un filtro al video
function apply_filter($input_path, $output_path, $filter) {
    $filter_cmd = "";

    switch ($filter) {
        case 'bn':
            $filter_cmd = "-vf colorchannelmixer=.3:.4:.3:0:.3:.4:.3:0:.3:.4:.3";
            break;
        case 'lum':
            $filter_cmd = "-vf eq=brightness=0.1:contrast=1.3";
            break;
        case 'cinematic':
            $filter_cmd = "-vf colorlevels=rimin=0.058:gimin=0.058:bimin=0.058:rimax=0.977:gimax=0.977:bimax=0.977";
            break;
        case 'cool':
            $filter_cmd = "-vf colortemperature=4000";
            break;
        case 'seppia':
            $filter_cmd = "-vf colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131";
            break;
        case 'vintage':
            $filter_cmd = "-vf colorlevels=rimin=0.125:gimin=0.125:bimin=0.125:rimax=0.75:gimax=0.75:bimax=0.75,vignette";
            break;
        default:
            $filter_cmd = "";
    }

    $cmd = "ffmpeg -i " . escapeshellarg($input_path) . " " . 
           $filter_cmd . " -c:v libx264 -c:a copy " . 
           escapeshellarg($output_path) . " 2>&1";
    exec($cmd, $output, $return_code);

    return $return_code === 0;
}

// Funzione per adattare il video al formato 9:16
function adapt_to_vertical($input_path, $output_path) {
    $cmd = "ffmpeg -i " . escapeshellarg($input_path) . 
           " -vf \"scale=720:-1,crop=720:1280:0:0\" " . 
           "-c:v libx264 -c:a copy " . escapeshellarg($output_path) . " 2>&1";
    exec($cmd, $output, $return_code);

    return $return_code === 0;
}

// Funzione per verificare e creare le cartelle
function create_directory($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true)) {
            echo "Errore: Impossibile creare la cartella $dir.";
            exit;
        }
    }
}

// Funzione per elaborare i file caricati
function process_uploads() {
    if (!is_ffmpeg_available()) {
        return [
            'success' => false,
            'message' => 'FFmpeg non è disponibile su questo server. Contatta l\'amministratore.'
        ];
    }

    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        return [
            'success' => false,
            'message' => 'Metodo non valido'
        ];
    }

    // Verifica i file caricati
    if (!isset($_FILES['files'])) {
        return [
            'success' => false,
            'message' => 'Nessun file caricato'
        ];
    }

    // Crea directory per i file temporanei
    $process_id = uniqid();
    $temp_dir = "uploads/" . $process_id;
    $output_dir = "results/" . $process_id;

    // Verifica la creazione delle cartelle
    create_directory("uploads");
    create_directory("results");
    create_directory($temp_dir);
    create_directory($output_dir);

    // Ottieni i parametri
    $video_duration = isset($_POST['videoDuration']) ? intval($_POST['videoDuration']) : 5;
    $filter = isset($_POST['filter']) ? $_POST['filter'] : 'none';

    $processed_files = [];
    $source_files = [];

    // Elabora ogni file
    $total_files = count($_FILES['files']['name']);

    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['files']['tmp_name'][$i];
            $name = $_FILES['files']['name'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Solo file video MP4
            if ($ext == 'mp4') {
                $source_path = $temp_dir . '/' . $name;
                move_uploaded_file($tmp_name, $source_path);
                $source_files[] = $source_path;

                // Taglia il video
                $trimmed_path = $temp_dir . '/trimmed_' . $name;
                if (trim_video($source_path, $trimmed_path, $video_duration)) {
                    // Applica filtro se specificato
                    $filtered_path = $filter != 'none' ? 
                        $temp_dir . '/filtered_' . $name : 
                        $trimmed_path;

                    if ($filter != 'none') {
                        if (!apply_filter($trimmed_path, $filtered_path, $filter)) {
                            $filtered_path = $trimmed_path; // Usa il file tagliato se il filtro fallisce
                        }
                    }

                    // Adatta a 9:16
                    $final_path = $temp_dir . '/final_' . $name;
                    if (adapt_to_vertical($filtered_path, $final_path)) {
                        $processed_files[] = $final_path;
                    } else {
                        $processed_files[] = $filtered_path; // Usa il file filtrato se l'adattamento fallisce
                    }
                }
            }
        }
    }

    if (empty($processed_files)) {
        return [
            'success' => false,
            'message' => 'Nessun file video valido elaborato'
        ];
    }

    // Concatena i video
    $output_file = $output_dir . '/montaggio_finale.mp4';
    if (concatenate_videos($processed_files, $output_file)) {
        // Crea una miniatura
        $thumbnail = $output_dir . '/thumbnail.jpg';
        create_thumbnail($output_file, $thumbnail);

        // Prepara l'URL per il download
        $download_url = 'results/' . $process_id . '/montaggio_finale.mp4';
        $thumbnail_url = 'results/' . $process_id . '/thumbnail.jpg';

        return [
            'success' => true,
            'message' => 'Video generato con successo',
            'video_url' => $download_url,
            'thumbnail_url' => $thumbnail_url
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Errore durante la concatenazione dei video'
        ];
    }
}

// Se questo script viene chiamato direttamente tramite AJAX
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    header('Content-Type: application/json');
    echo json_encode(process_uploads());
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Montaggio Video con FFmpeg</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .progress {
            height: 25px;
            margin: 15px 0;
        }
        #uploadList {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Montaggio Video Automatico</h1>
        <div class="alert alert-info">
            <strong>Nota:</strong> Questa versione semplificata supporta solo file MP4 e alcune funzionalità sono limitate.
        </div>

        <form id="videoForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fileUpload">📂 Seleziona video</label>
                <input type="file" id="fileUpload" name="files[]" multiple class="form-control">
            </div>
            <div class="form-group">
                <label for="videoDuration">Durata video (secondi)</label>
                <input type="number" id="videoDuration" name="videoDuration" class="form-control" value="5" min="1">
            </div>
            <div class="form-group">
                <label for="filter">Filtro video</label>
                <select id="filter" name="filter" class="form-control">
                    <option value="none">Nessun filtro</option>
                    <option value="bn">Bianco e nero</option>
                    <option value="lum">Luminosità alta</option>
                    <option value="cinematic">Cinematica</option>
                    <option value="cool">Cool</option>
                    <option value="seppia">Seppia</option>
                    <option value="vintage">Vintage</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Carica video</button>
        </form>
        <div id="uploadList"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('videoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            let xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);

            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable) {
                    let percent = (event.loaded / event.total) * 100;
                    console.log('Progress: ' + percent + '%');
                }
            };

            xhr.onload = function() {
                if (xhr.status === 200) {
                    let response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert(response.message);
                        let videoList = document.getElementById('uploadList');
                        let newItem = document.createElement('div');
                        newItem.className = 'file-item';
                        newItem.innerHTML = `<a href="${response.video_url}" target="_blank">Video montato</a> | <a href="${response.thumbnail_url}" target="_blank">Miniatura</a>`;
                        videoList.appendChild(newItem);
                    } else {
                        alert('Errore: ' + response.message);
                    }
                } else {
                    alert('Errore durante il caricamento dei file');
                }
            };

            xhr.send(formData);
        });
    </script>
</body>
</html>
