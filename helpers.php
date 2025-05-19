<?php
// File: helpers.php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

function ensureDir(string $dir): void {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

function getConfig(): array {
    static $cfg;
    return $cfg ?: $cfg = require __DIR__ . '/config.php';
}

function logger(): Logger {
    static $log;
    if (!$log) {
        $cfg = getConfig();
        $log = new Logger('app');
        ensureDir(dirname($cfg['logging']['file']));
        $log->pushHandler(new StreamHandler($cfg['logging']['file'], Logger::DEBUG));
    }
    return $log;
}

function validateFiles(array $files): array {
    $cfg = getConfig();
    $valid = [];
    foreach ($files['error'] as $i => $err) {
n        if ($err === UPLOAD_ERR_OK && $files['size'][$i] <= $cfg['system']['max_upload_size']) {
            $tmp = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['mp4','mov','avi','mkv'])) {
                $dest = $cfg['paths']['upload_dir'] . $name;
                if (move_uploaded_file($tmp, $dest)) $valid[] = $dest;
            }
        }
    }
    return $valid;
}
?>