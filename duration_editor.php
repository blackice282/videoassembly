<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';

/**
 * Applica modifica di durata (taglio o velocizzazione) a un video
 *
 * @param string $inputPath Percorso del file video
 * @param int $targetDuration Durata target in secondi
 * @param string $method Metodo ('trim' o 'speed')
 * @return string Percorso nuovo file video
 */
function applyDurationEdit($inputPath, $targetDuration, $method = 'trim') {
    if ($method === 'trim') {
        $outputPath = TEMP_DIR . '/trim_' . uniqid() . '.mp4';
        $cmd = sprintf('%s -y -i "%s" -t %d -c copy "%s"',
            FFMPEG_PATH, $inputPath, $targetDuration, $outputPath);
        shell_exec($cmd);
    } elseif ($method === 'speed') {
        $originalDuration = getVideoDuration($inputPath);
        if ($originalDuration <= 0) {
            return $inputPath;
        }
        $speedFactor = $originalDuration / $targetDuration;
        $outputPath = TEMP_DIR . '/speed_' . uniqid() . '.mp4';
        $cmd = sprintf('%s -y -i "%s" -filter_complex "[0:v]setpts=PTS/%.2f[v];[0:a]atempo=%.2f[a]" -map "[v]" -map "[a]" "%s"',
            FFMPEG_PATH, $inputPath, $speedFactor, min(max($speedFactor, 0.5), 2.0), $outputPath);
        shell_exec($cmd);
    } else {
        return $inputPath;
    }

    return file_exists($outputPath) ? $outputPath : $inputPath;
}

/**
 * Ottiene la durata di un video
 */
function getVideoDuration($filePath) {
    $cmd = sprintf('%s -i "%s" 2>&1', FFMPEG_PATH, $filePath);
    $output = shell_exec($cmd);
    if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $output, $matches)) {
        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = (float)$matches[3];
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
    return 0;
}
?>
