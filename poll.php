<?php
require __DIR__ . '/helpers.php';
$status = getStatus($_GET['jobId'] ?? '');
if (!$status) { http_response_code(404); echo 'Job non trovato'; exit; }
header('Content-Type: application/json');
echo json_encode($status);
?>