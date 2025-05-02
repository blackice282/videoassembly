<?php
// monta.php - Gestione upload e montaggio video con trimming
require 'config.php';
require 'duration_editor.php';

if (!isset($_FILES['video'])) {
    http_response_code(400);
    echo "Nessun file video caricato.";
    exit;
}

$durationMin = intval($_POST['duration']);
$uploadDir = 'temp/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$originalName = basename($_FILES['video']['name']);
$uploadPath = $uploadDir . uniqid() . '_' . $originalName;

if (!move_uploaded_file($_FILES['video']['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo "Errore nel salvataggio del file.";
    exit;
}

// Percorso del file montato (senza trimming)
// Qui potresti chiamare altre funzioni per effetti, transizioni, ecc.
// Per semplicità, usiamo direttamente il file caricato come input per il trimming
$trimmedDir = 'temp/';
$trimmedPath = $trimmedDir . uniqid() . '_trimmed_' . $originalName;

$durationSec = $durationMin * 60;
if (!trimVideo($uploadPath, $trimmedPath, $durationSec)) {
    http_response_code(500);
    echo "Errore nel trimming del video.";
    exit;
}

// Restituisci il video finale
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="video_trimmed.mp4"');
readfile($trimmedPath);
exit;
?>