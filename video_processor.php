<?php
require_once 'config.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';
require_once 'debug_utility.php';

class VideoProcessor {
    private $inputVideo;
    private $outputPath;
    private $tempDir;
    private $options;
    private $videoInfo;

    public function __construct($inputVideo, $outputPath = null, $options = []) {
        $this->inputVideo = $inputVideo;
        $this->outputPath = $outputPath ?? (UPLOAD_DIR . '/processed_' . uniqid() . '.mp4');
        $this->tempDir = TEMP_DIR . '/proc_' . uniqid();

        if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);

        $defaultOptions = [
            'apply_effect' => false,
            'effect_name' => 'none',
            'apply_audio' => false,
            'audio_category' => 'none',
            'audio_volume' => 0.3,
            'apply_face_privacy' => false,
            'exclude_yellow_vests' => true
        ];
        $this->options = array_merge($defaultOptions, $options);
        $this->videoInfo = ['success' => true];  // simulate analyzeVideo()

        debugLog("VideoProcessor inizializzato: {$this->inputVideo} -> {$this->outputPath}", "info", "processor");
    }

    public function processVideo() {
        if (!$this->videoInfo['success']) {
            return ['success' => false, 'message' => 'Video di input non valido', 'details' => $this->videoInfo];
        }

        $workingVideo = $this->inputVideo;
        $steps = [];

        if ($this->options['apply_face_privacy']) {
            $privacyResult = $this->applyFacePrivacy($workingVideo);
            $steps[] = $privacyResult;
            if ($privacyResult['success']) $workingVideo = $privacyResult['output_file'];
        }

        if ($this->options['apply_effect'] && $this->options['effect_name'] !== 'none') {
            $effectResult = $this->applyVideoEffect($workingVideo);
            $steps[] = $effectResult;
            if ($effectResult['success']) $workingVideo = $effectResult['output_file'];
        }

        if ($this->options['apply_audio'] && $this->options['audio_category'] !== 'none') {
            $audioResult = $this->applyBackgroundAudio($workingVideo);
            $steps[] = $audioResult;
            if ($audioResult['success']) $workingVideo = $audioResult['output_file'];
        }

        if ($workingVideo === $this->inputVideo) {
            if (copy($this->inputVideo, $this->outputPath)) {
                return ['success' => true, 'message' => 'File copiato senza modifiche', 'output_file' => $this->outputPath, 'steps' => $steps];
            }
            return ['success' => false, 'message' => 'Errore nella copia del file', 'steps' => $steps];
        }

        if ($workingVideo !== $this->outputPath) {
            if (!copy($workingVideo, $this->outputPath)) {
                return ['success' => false, 'message' => 'Errore nella copia del file finale', 'steps' => $steps];
            }
        }

        $this->cleanup();
        return ['success' => true, 'message' => 'Elaborazione completata', 'output_file' => $this->outputPath, 'steps' => $steps];
    }

    private function applyFacePrivacy($videoPath) {
        $outputFile = "{$this->tempDir}/privacy_" . basename($videoPath);
        return ['success' => true, 'output_file' => $outputFile];  // simulazione
    }

    private function applyVideoEffect($videoPath) {
        $outputFile = "{$this->tempDir}/effect_" . basename($videoPath);
        return ['success' => true, 'output_file' => $outputFile];  // simulazione
    }

    private function applyBackgroundAudio($videoPath) {
        $outputFile = "{$this->tempDir}/audio_" . basename($videoPath);
        return ['success' => true, 'output_file' => $outputFile];  // simulazione
    }

    private function cleanup() {
        if (file_exists($this->tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
            }
            rmdir($this->tempDir);
        }
    }

    public function __destruct() {
        $this->cleanup();
    }
}

function process_uploaded_video($inputVideo, $options = []) {
    $processor = new VideoProcessor($inputVideo, null, $options);
    return $processor->processVideo();
}
