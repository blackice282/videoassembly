<?php
// index.php

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['video'])) {
    $video = $_FILES['video'];
    $videoPath = 'uploads/' . $video['name'];

    // Salva il video temporaneamente
    move_uploaded_file($video['tmp_name'], $videoPath);

    // Includi lo script di elaborazione
    include 'ffmpeg_script.php';

    // Processa il video
    $output = process_video($videoPath);

    if ($output['success']) {
        echo json_encode([
            'success' => true,
            'video_url' => $output['video_url'],
            'thumbnail_url' => $output['thumbnail_url']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $output['message']]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
}

?>
