<?php
function ensureDir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function handleUploads($uploadDir, $maxSize) {
    ensureDir($uploadDir);
    $saved = [];
    foreach ($_FILES as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) continue;
        if ($file['size'] > $maxSize) continue;
        $dest = $uploadDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $dest)) $saved[] = $dest;
    }
    return $saved;
}

function convertToTs($file, $config) {
    $tempDir = $config['paths']['temp_dir'];
    ensureDir($tempDir);
    $out = $tempDir . basename($file) . '.ts';
    $cmd = sprintf(
        'ffmpeg -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($file),
        escapeshellarg($out)
    );
    exec($cmd);
    return $out;
}

function cleanupTempFiles($files, $tempDir) {
    foreach ($files as $f) {
        if (file_exists($f)) unlink($f);
    }
    if (is_dir($tempDir) && count(scandir($tempDir)) <= 2) rmdir($tempDir);
}
?>