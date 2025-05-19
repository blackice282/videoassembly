<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/ffmpeg_script.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/transitions.php';
require __DIR__ . '/duration_editor.php';

// 1. Upload handling
$uploadedFiles = handleUploads($config['paths']['upload_dir'], $config['system']['max_upload_size']);

// 2. Scene detection (optional)
$segments = [];
if (isset($_POST['detect_people']) && $_POST['detect_people']) {
    $segments = detectMovingPeople(
        $uploadedFiles,
        $config['detection'],
        $config['paths']['temp_dir']
    );
} else {
    foreach ($uploadedFiles as $file) {
        // convert each to .ts for concat
        $segments[] = convertToTs($file, $config);
    }
}

// 3. Apply transitions + concatenate
if ($config['transitions']['enabled']) {
    $finalTs = concatenateWithTransitions(
        $segments,
        $config['transitions'],
        $config['paths']['temp_dir']
    );
} else {
    $finalTs = concatenateTsSegments($segments, $config['paths']['temp_dir']);
}

// 4. Duration adaptation
$targetDuration = floatval($_POST['target_duration'] ?? 0);
$finalTs = adaptDuration(
    $finalTs,
    $targetDuration,
    $config['paths']['temp_dir']
);

// 5. Remux to output and thumbnail
$outputFile = $config['paths']['output_dir'] . 'final_' . time() . '.mp4';
processVideo(
    $finalTs,
    $outputFile,
    $config['paths']['output_dir'],
    $config['system']['base_url']
);

// 6. Cleanup all temp
cleanupTempFiles(
    array_merge($segments, [$finalTs]),
    $config['paths']['temp_dir']
);

// 7. Response
echo json_encode([
    'video_url'     => $config['system']['base_url'] . '/output/' . basename($outputFile),
    'thumbnail_url' => $config['system']['base_url'] . '/output/' . basename($outputFile, '.mp4') . '.jpg',
]);
?>