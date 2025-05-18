<?php
// upload.php - Backend processing video assembly with real-time debug output

// Disable output buffering and enable implicit flush
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

header('Content-Type: text/plain; charset=utf-8');

echo "Ricevuti dati: " . print_r($_POST, true) . "\n";
if (!isset($_FILES['video'])) {
    echo "Errore: nessun file video ricevuto.\n";
    exit;
}

$count = count($_FILES['video']['name']);
echo "Inizio upload di {$count} file...\n";

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "Cartella uploads creata.\n";
}

$uploadedPaths = [];
foreach ($_FILES['video']['error'] as $i => $error) {
    $name = $_FILES['video']['name'][$i];
    echo "Upload file {$i}: {$name}... ";
    if ($error === UPLOAD_ERR_OK) {
        $tmp = $_FILES['video']['tmp_name'][$i];
        $target = $uploadDir . basename($name);
        if (move_uploaded_file($tmp, $target)) {
            echo "OK\n";
            $uploadedPaths[] = $target;
        } else {
            echo "Errore spostamento.\n";
        }
    } else {
        echo "Errore codice {$error}.\n";
    }
}

echo "Montaggio in corso...\n";
// --- QUI inserisci la logica di montaggio video ---
// esempio: chiamata a script esterno o funzione PHP
// per ora simuliamo con sleep
sleep(2);
echo "Montaggio completato.\n";

// Genera URL di download finale
// Assumendo che il file montato sia in processed/video_final.mp4
$url = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && 
        strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] : '') . 
    dirname($_SERVER['SCRIPT_NAME']) . "/processed/video_final.mp4";

echo "URL finale: {$url}\n";

exit;
