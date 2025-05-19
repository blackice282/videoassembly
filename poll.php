<?php
// File: poll.php
require __DIR__ . '/helpers.php';
$cfg = getConfig();
$jid = $_GET['jobId'] ?? '';
$path = $cfg['paths']['temp_dir'] . "$jid.json";
if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(['error'=>'Job not found']);
    exit;
}
$data = json_decode(file_get_contents($path), true);
header('Content-Type: application/json');
echo json_encode($data);
?>