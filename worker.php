<?php
// worker.php
require 'config.php';
require 'ffmpeg_script.php';

$uploadDir = getConfig('paths.uploads');
$outputDir = getConfig('paths.output');

// Process all pending job files
$jobs = glob("$uploadDir/*.job");
foreach ($jobs as $jobFile) {
    $job = json_decode(file_get_contents($jobFile), true);
    $input = $job['input'];
    $output = "$outputDir/processed_" . basename($input);
    $duration = $job['duration'];

    $success = processVideo($input, $output, $duration);
    if ($success) {
        file_put_contents("$jobFile.done", json_encode(['output' => $output]));
    } else {
        file_put_contents("$jobFile.done", json_encode(['error' => 'Processing failed']));
    }
    unlink($jobFile);
}
?>