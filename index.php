<?php
// File: index.php (frontend entry)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents(__DIR__ . '/result.php');
    exit;
}

// POST => create job
require __DIR__ . '/helpers.php';
$cfg = getConfig();
ensureDir($cfg['paths']['upload_dir']);
ensureDir($cfg['paths']['temp_dir']);
ensureDir($cfg['paths']['output_dir']);

$videos = validateFiles($_FILES['videos'] ?? []);
if (empty($videos)) {
    http_response_code(400);
    echo json_encode(['error'=>'No valid videos']);
    exit;
}

$jobId = uniqid('job_', true);
$meta = [
    'videos'  => $videos,
    'detect'  => !empty($_POST['detect_people']),
    'target'  => floatval($_POST['target_duration'] ?? 0),
    'codec'   => [
        'crf'    => intval($_POST['crf'] ?? $cfg['codec']['crf']),
        'preset' => $_POST['preset'] ?? $cfg['codec']['preset'],
    ],
    'transitions' => [
        'enabled' => !empty($_POST['transitions_enabled']),
        'type'    => $_POST['transition_type'] ?? $cfg['transitions']['type'],
        'duration'=> floatval($_POST['transition_duration'] ?? $cfg['transitions']['duration']),
    ],
];
file_put_contents(
    $cfg['paths']['temp_dir'] . "$jobId.json",
    json_encode(array_merge($meta, ['status'=>'queued', 'progress'=>0]))
);
// dispatch background
exec("php worker.php $jobId > /dev/null 2>&1 &");
header('Content-Type: application/json');
echo json_encode(['jobId'=>$jobId]);
?>