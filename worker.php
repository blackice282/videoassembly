<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/ffmpeg_script.php';

$jobId = $argv[1];
saveStatus($jobId, 'processing', 10);

$videos = glob($config['paths']['upload_dir'] . '*.mp4');
$segments = detectMovingPeople($videos, $config, $config['paths']['temp_dir']);
saveStatus($jobId, 'processing', 30);

$combined = $config['paths']['temp_dir'] . 'combined.ts';
file_put_contents($config['paths']['temp_dir'].'concat_list.txt', '');
foreach ($segments as $seg) {
    file_put_contents($config['paths']['temp_dir'].'concat_list.txt', "file '$seg'\n", FILE_APPEND);
}
exec(sprintf('ffmpeg -f concat -safe 0 -i %s -c copy %s',
    escapeshellarg($config['paths']['temp_dir'].'concat_list.txt'),
    escapeshellarg($combined)
));
saveStatus($jobId, 'processing', 60);

$output = $config['paths']['output_dir'] . "$jobId.mp4";
processVideo($combined, $output, $config['paths']['output_dir']);
saveStatus($jobId, 'done', 100, $config['system']['base_url'].'/output/'.$jobId.'.mp4');
?>