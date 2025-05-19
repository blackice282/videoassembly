<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/ffmpeg_script.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/transitions.php';
require __DIR__ . '/duration_editor.php';

// Ensure dirs
ensureDir($config['paths']['upload_dir']);
ensureDir($config['paths']['temp_dir']);
ensureDir($config['paths']['output_dir']);

// 1. Upload
$uploaded = handleUploads($config['paths']['upload_dir'], $config['system']['max_upload_size']);
if (empty($uploaded)) {
    http_response_code(400);
    echo json_encode(['error'=>'No valid uploads']);
    exit;
}

// 2. Detection or convert
$segments = [];
if (!empty($_POST['detect_people'])) {
    $segments = detectMovingPeople($uploaded, $config['detection'], $config['paths']['temp_dir']);
} else {
    foreach ($uploaded as $u) {
        $segments[] = convertToTs($u, $config);
    }
}

// 3. Transitions & concat
if ($config['transitions']['enabled']) {
    $combined = concatenateWithTransitions($segments, $config['transitions'], $config['paths']['temp_dir']);
} else {
    $combined = concatenateTsSegments($segments, $config['paths']['temp_dir']);
}

// 4. Adapt duration
$target = floatval($_POST['target_duration'] ?? 0);
$adapted = adaptDuration($combined, $target, $config['paths']['temp_dir']);

// 5. Remux & thumbnail
$outMp4 = $config['paths']['output_dir'].'final_'.time().'.mp4';
processVideo($adapted, $outMp4, $config['paths']['output_dir'], $config['system']['base_url']);

// 6. Cleanup
cleanupTempFiles(array_merge($segments, [$combined, $adapted]), $config['paths']['temp_dir']);

// 7. Response
echo json_encode([
    'video_url'=>$config['system']['base_url'].'/output/'.basename($outMp4),
    'thumbnail_url'=>$config['system']['base_url'].'/output/'.basename($outMp4,'.mp4').'.jpg'
]);
?>