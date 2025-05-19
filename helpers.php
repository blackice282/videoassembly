<?php
function ensureDir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
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
            if (move_uploaded_file($tmp, $dest)) {
                $saved[] = $dest;
            }
        }
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

function concatTsSegments($segments, $tempDir) {
    ensureDir($tempDir);
    $list = $tempDir . '/concat_list.txt';
    $fp = fopen($list, 'w');
    foreach ($segments as $seg) {
        fwrite($fp, "file '" . str_replace("'", "\'", $seg) . "'\n");
    }
    fclose($fp);
    $out = $tempDir . '/combined.ts';
    $cmd = sprintf(
        'ffmpeg -f concat -safe 0 -i %s -c copy %s',
        escapeshellarg($list),
        escapeshellarg($out)
    );
    exec($cmd);
    return $out;
}

function cleanupTemp($files, $tempDir) {
    foreach ($files as $f) {
        if (file_exists($f)) {
            unlink($f);
        }
    }
    if (is_dir($tempDir) && count(scandir($tempDir)) <= 2) {
        rmdir($tempDir);
    }
}
?>