<?php
// duration_editor.php - Versione ottimizzata
require_once 'config.php';

/**
 * Adatta i segmenti per ottenere un video della durata desiderata
 * 
 * @param array $segmentFiles Array dei file dei segmenti
 * @param int $targetDuration Durata desiderata in secondi
 * @param string $outputDirectory Directory per i file temporanei
 * @param array $segmentsInfo Informazioni aggiuntive sui segmenti (persone, ecc.)
 * @return array File dei segmenti adattati
 */
function adaptSegmentsToDuration($segmentFiles, $targetDuration, $outputDirectory, $segmentsInfo = []) {
    if (empty($segmentFiles)) {
        return [];
    }
    
    if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
    
    $segments = [];
    $totalDuration = 0;

    foreach ($segmentFiles as $file) {
        $duration = getVideoDuration($file);
        $segments[] = ['file' => $file, 'duration' => $duration];
        $totalDuration += $duration;
    }

    if ($totalDuration <= $targetDuration) {
        return array_column($segments, 'file');
    }

    // Ritaglia i segmenti in modo proporzionale
    $adjustedFiles = [];
    $accumulated = 0;
    foreach ($segments as $index => $seg) {
        $ratio = $seg['duration'] / $totalDuration;
        $newDuration = floor($ratio * $targetDuration);

        if ($newDuration < 1) continue;

        $outputPath = $outputDirectory . '/trimmed_' . $index . '.mp4';
        trimVideo($seg['file'], $outputPath, 0, $newDuration);
        $adjustedFiles[] = $outputPath;
        $accumulated += $newDuration;

        if ($accumulated >= $targetDuration) break;
    }

    return $adjustedFiles;
}

/**
 * Ottiene la durata di un file video tramite ffprobe
 */
function getVideoDuration($filePath) {
    $command = sprintf("%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"", FFPROBE_PATH, $filePath);
    $duration = shell_exec($command);
    return floatval(trim($duration));
}

/**
 * Ritaglia un video a una determinata durata
 */
function trimVideo($inputPath, $outputPath, $start, $duration) {
    $cmd = sprintf('%s -y -ss %d -i "%s" -t %d -c copy "%s"', FFMPEG_PATH, $start, $inputPath, $duration, $outputPath);
    shell_exec($cmd);
}
