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
    // Crea un file di lista per FFmpeg
    $list_file = "temp_list_" . uniqid() . ".txt";
    $list_content = "";
    
    foreach ($video_files as $file) {
        $list_content .= "file '" . str_replace("'", "'\\''", $file) . "'\n";
    }
    
    file_put_contents($list_file, $list_content);
    
    // Concatena i video
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($list_file) . 
           " -c copy " . escapeshellarg($output_path) . " 2>&1";
    exec($cmd, $output, $return_code);
    
    // Pulisci
    unlink($list_file);
    
    return $return_code === 0;
}

// Elabora i file caricati
function process_uploads() {
    // Verifica se è una richiesta POST
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        return [
            'success' => false,
            'message' => 'Metodo non valido'
        ];
    }
    
    // Verifica se i file sono stati caricati
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
    
    if (!file_exists("uploads")) mkdir("uploads");
    if (!file_exists("results")) mkdir("results");
    if (!file_exists($temp_dir)) mkdir($temp_dir);
    if (!file_exists($output_dir)) mkdir($output_dir);
    
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
                if (trim_video($source_path, $trimmed_path, 5)) {
                    // Aggiungi il video tagliato alla lista dei file processati
                    $processed_files[] = $trimmed_path;
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
            <strong>Nota:</strong> Questa versione semplificata supporta solo file MP4.
        </div>
        
        <form id="videoForm" enctype="multipart/form-data" method="POST">
            <div class="form-group">
                <label for="fileUpload">📂 Seleziona video</label>
                <input type="file" id="fileUpload" name="files[]" multiple>
            </div>
            <button type="submit" class="btn btn-primary">Carica Video</button>
        </form>

        <div id="uploadList" class="mt-4">
            <h4>Video caricati</h4>
        </div>
    </div>

    <script>
        document.getElementById('videoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            let xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);  // Questo invia la richiesta con il metodo POST

            xhr.onload = function() {
                if (xhr.status === 200) {
                    let response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert(response.message);
                        let videoList = document.getElementById('uploadList');
                        let newItem = document.createElement('div');
                        newItem.className = 'file-item';
                        newItem.innerHTML = `<a href="${response.video_url}" target="_blank">Scarica Video Montato</a> | <a href="${response.thumbnail_url}" target="_blank">Miniatura</a>`;
                        videoList.appendChild(newItem);
                    } else {
                        alert('Errore: ' + response.message);
                    }
                } else {
                    alert('Errore durante il caricamento dei file');
                }
            };

            xhr.send(formData);  // Invia il modulo via POST
        });
    </script>
</body>
</html>
