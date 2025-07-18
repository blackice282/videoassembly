// index.php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// Include script opzionali solo se presenti
$scripts = ['ffmpeg_script.php', 'people_detection.php', 'transitions.php', 'duration_editor.php'];
foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (file_exists($path)) {
        require_once $path;
    }
}

function createUploadsDir(): void {
    $uploads = getConfig('paths.uploads', 'uploads');
    $temp    = getConfig('paths.temp', 'temp');
    if (!file_exists($uploads)) mkdir($uploads, 0777, true);
    if (!file_exists($temp))    mkdir($temp,    0777, true);
}

if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $file) {
            if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
                @unlink($file);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createUploadsDir();
    set_time_limit(300);

    // ... qui tutta la logica di caricamento, conversione, montaggio ...

    // Pulizia file temporanei
    cleanupTempFiles($uploaded_ts_files, getConfig('system.keep_original', true));
    cleanupTempFiles($segments_to_process, getConfig('system.keep_original', true));
}
?>

// ffmpeg_script.php
<?php
// Protegge contro doppie dichiarazioni di cleanupTempFiles
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei (generico e FFmpeg)
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $file) {
            if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
                @unlink($file);
            }
        }
    }
}

/**
 * Esegue un comando FFmpeg catturandone output ed errori
 *
 * @param string $cmd
 * @throws RuntimeException se FFmpeg restituisce errore
 */
function runFfmpegCommand(string $cmd): void {
    exec($cmd . ' 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        throw new RuntimeException('FFmpeg error: ' . implode("\n", $output));
    }
}

/**
 * Converte un file video in formato .ts
 *
 * @param string $inputPath
 * @param string $outputTsPath
 */
function convertToTs(string $inputPath, string $outputTsPath): void {
    $cmd = sprintf(
        'ffmpeg -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputPath),
        escapeshellarg($outputTsPath)
    );
    runFfmpegCommand($cmd);
}

/**
 * Concatena segmenti .ts, aggiunge traccia audio e genera output.mp4
 *
 * @param string[]      $tsFiles
 * @param string        $outputPath
 * @param string|null   $audioPath
 */
function concatenateTsFiles(array $tsFiles, string $outputPath, ?string $audioPath = null): void {
    // Crea file temporaneo con lista dei segmenti
    $listFile = sys_get_temp_dir() . '/concat_' . uniqid() . '.txt';
    $lines = array_map(fn($f) => "file '" . addslashes($f) . "'", $tsFiles);
    file_put_contents($listFile, implode("\n", $lines));

    // Costruisce comando FFmpeg per concatenazione + audio
    $audioArgs = $audioPath
        ? sprintf('-i %s -c:a aac -shortest', escapeshellarg($audioPath))
        : '';
    $cmd = sprintf(
        'ffmpeg -f concat -safe 0 -i %s -c copy %s %s',
        escapeshellarg($listFile),
        $audioArgs,
        escapeshellarg($outputPath)
    );
    runFfmpegCommand($cmd);
    @unlink($listFile);
}
?>
