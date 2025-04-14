<?php
function createSessionFolder($base = 'uploads') {
    $session_id = uniqid();
    $folder = "$base/$session_id";
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    return $folder;
}

function getErrorMessage($error_code, $filename) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => "Il file $filename eccede la dimensione massima consentita.",
        UPLOAD_ERR_FORM_SIZE  => "Il file $filename eccede la dimensione massima definita nel modulo.",
        UPLOAD_ERR_PARTIAL    => "Il file $filename è stato caricato solo parzialmente.",
        UPLOAD_ERR_NO_FILE    => "Nessun file caricato per $filename.",
        UPLOAD_ERR_NO_TMP_DIR => "Manca una cartella temporanea per $filename.",
        UPLOAD_ERR_CANT_WRITE => "Impossibile scrivere su disco per $filename.",
        UPLOAD_ERR_EXTENSION  => "Il caricamento del file $filename è stato interrotto da un'estensione PHP.",
    ];
    return $errors[$error_code] ?? "Errore sconosciuto per il file $filename.";
}

$upload_messages = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['files'])) {
    $total_files = count($_FILES['files']['name']);
    $upload_dir = createSessionFolder();

    for ($i = 0; $i < $total_files; $i++) {
        $error = $_FILES['files']['error'][$i];
        $name = basename($_FILES['files']['name'][$i]);

        if ($error !== UPLOAD_ERR_OK) {
            $upload_messages[] = getErrorMessage($error, $name);
            continue;
        }

        $tmp_name = $_FILES['files']['tmp_name'][$i];
        $destination = "$upload_dir/$name";

        if (move_uploaded_file($tmp_name, $destination)) {
            $upload_messages[] = "✅ File caricato correttamente: $name";
        } else {
            $upload_messages[] = "❌ Errore nel salvataggio del file: $name";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carica Video</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 2rem auto;
        }
        input[type="file"] {
            margin: 1rem 0;
        }
        .message {
            background: #f2f2f2;
            border-left: 5px solid #333;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <h1>Carica i tuoi video</h1>

    <?php if (!empty($upload_messages)): ?>
        <div class="message">
            <?php foreach ($upload_messages as $msg): ?>
                <p><?= htmlspecialchars($msg) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="fileUpload">Seleziona uno o più video:</label><br>
        <input type="file" name="files[]" id="fileUpload" multiple accept="video/mp4"><br>
        <button type="submit">Carica</button>
    </form>
</body>
</html>
