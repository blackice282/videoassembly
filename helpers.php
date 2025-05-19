<?php
function ensureDir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

function handleUploads($uploadDir, $maxSize) {
    ensureDir($uploadDir);
    $saved = [];
    if (!isset($_FILES['videos'])) return $saved;
    foreach ($_FILES['videos']['error'] as $i => $err) {
        if ($err === UPLOAD_ERR_OK && $_FILES['videos']['size'][$i] <= $maxSize) {
            $tmp  = $_FILES['videos']['tmp_name'][$i];
            $name = basename($_FILES['videos']['name'][$i]);
            $dest = $uploadDir . $name;
            if (move_uploaded_file($tmp, $dest)) $saved[] = $dest;
        }
    }
    return $saved;
}

function saveStatus($jobId, $status, $progress, $url = null) {
    $cfg = require __DIR__ . '/config.php';
    ensureDir($cfg['paths']['temp_dir']);
    $file = $cfg['paths']['temp_dir'] . "$jobId.json";
    file_put_contents($file, json_encode([
        'status' => $status,
        'progress' => $progress,
        'video_url' => $url
    ]));
}

function getStatus($jobId) {
    $cfg = require __DIR__ . '/config.php';
    $file = $cfg['paths']['temp_dir'] . "$jobId.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}
?>