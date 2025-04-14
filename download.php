<?php
$file = __DIR__ . '/uploads/final_video.mp4';

if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="montaggio_finale.mp4"');
    header('Content-Length: ' . filesize($file));
    flush();
    readfile($file);
    exit;
} else {
    echo "❌ File non trovato.";
}
