<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include 'result.php';
    exit;
}

require __DIR__ . '/people_detection.php';
require __DIR__ . '/ffmpeg_script.php';

ensureDir($config['paths']['upload_dir']);
ensureDir($config['paths']['temp_dir']);
ensureDir($config['paths']['output_dir']);

$videos = handleUploads($config['paths']['upload_dir'], $config['system']['max_upload_size']);
if (empty($videos)) { http_response_code(400); echo 'Nessun file caricato'; exit; }

$jobId = uniqid('job_');
saveStatus($jobId, 'queued', 0);

exec(sprintf('php worker.php %s > /dev/null 2>&1 &', escapeshellarg($jobId)));

header('Content-Type: application/json');
echo json_encode(['jobId' => $jobId]);
?>