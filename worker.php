<?php
// File: worker.php
require __DIR__ . '/helpers.php';
require __DIR__ . '/ffmpeg_script.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/transitions.php';
require __DIR__ . '/duration_editor.php';
$cfg = getConfig();
$jid = $argv[1];
$meta = json_decode(file_get_contents($cfg['paths']['temp_dir'] . "$jid.json"), true);
$pathMeta = $cfg['paths']['temp_dir'] . "$jid.json";

try {
    logger()->info("Job $jid started");
    $meta['status'] = 'processing'; $meta['progress']=10;
    file_put_contents($pathMeta, json_encode($meta));

    // 1. segments
    $segments = $meta['detect']
        ? detectMovingPeople($meta['videos'], $cfg['detection'], $cfg['paths']['temp_dir'])
        : array_map(fn($v)=>convertToTs($v,$cfg), $meta['videos']);
    $meta['progress']=30; file_put_contents($pathMeta, json_encode($meta));

    // 2. concat/trans
    $combined = $meta['transitions']['enabled']
        ? concatenateWithTransitions($segments, $meta['transitions'], $cfg['paths']['temp_dir'])
        : concatenateTsSegments($segments, $cfg['paths']['temp_dir']);
    $meta['progress']=50; file_put_contents($pathMeta, json_encode($meta));

    // 3. adapt
    $adapted = adaptDuration($combined, $meta['target'], $cfg['paths']['temp_dir']);
    $meta['progress']=70; file_put_contents($pathMeta, json_encode($meta));

    // 4. remux & upload
    $outName = "$jid.mp4";
    $outPath = $cfg['paths']['output_dir'] . $outName;
    processVideo($adapted, $outPath, $cfg['paths']['output_dir'], $cfg['system']['base_url']);
    // upload to S3 if configured
    if ($cfg['storage']['s3_bucket']) {
        $s3 = new \Aws\S3\S3Client([
            'region'  => $cfg['storage']['s3_region'],
            'version' => 'latest',
            'credentials'=>[
                'key'=> $cfg['storage']['s3_key'],
                'secret'=> $cfg['storage']['s3_secret'],
            ]
        ]);
        $s3->putObject([ 'Bucket'=> $cfg['storage']['s3_bucket'], 'Key'=>$outName, 'SourceFile'=>$outPath ]);
        $meta['video_url'] = $s3->getObjectUrl($cfg['storage']['s3_bucket'], $outName);
    } else {
        $meta['video_url'] = $cfg['system']['base_url'] . '/output/' . $outName;
    }
    $meta['thumbnail_url'] = str_replace('.mp4','.jpg',$meta['video_url']);
    $meta['status']='done'; $meta['progress']=100;
    file_put_contents($pathMeta, json_encode($meta));
    logger()->info("Job $jid completed");
} catch (\Throwable $e) {
    $meta['status']='error'; $meta['error']=$e->getMessage();
    file_put_contents($pathMeta, json_encode($meta));
    logger()->error("Job $jid error: {$e->getMessage()}");
}
?>