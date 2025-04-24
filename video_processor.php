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

    public function __construct($inputVideo, $outputPath = null, $options = []) {
        $this->inputVideo = $inputVideo;
        $this->outputPath = $outputPath ?? (UPLOAD_DIR . '/processed_' . uniqid() . '.mp4');
        $this->tempDir = TEMP_DIR . '/proc_' . uniqid();
        if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);

        $this->options = array_merge([
            'apply_face_privacy' => false,
            'apply_effect' => false,
            'effect_name' => 'none',
            'apply_audio' => false,
            'audio_category' => 'none',
            'audio_volume' => 0.3
        ], $options);
    }

    public function processVideo() {
        $workingVideo = $this->inputVideo;
        $steps = [];

        if ($this->options['apply_face_privacy']) {
            $output = "{$this->tempDir}/privacy.mp4";
            if (applyFacePrivacy($workingVideo, $output)) {
                $workingVideo = $output;
                $steps[] = 'face_privacy';
            }
        }

        if ($this->options['apply_effect'] && $this->options['effect_name'] !== 'none') {
            $output = "{$this->tempDir}/effect.mp4";
            if (applyVideoEffect($workingVideo, $output, $this->options['effect_name'])) {
                $workingVideo = $output;
                $steps[] = 'effect';
            }
        }

        if ($this->options['apply_audio'] && $this->options['audio_category'] !== 'none') {
            $output = "{$this->tempDir}/audio_mix.mp4";
            $audio = getRandomAudioFromCategory($this->options['audio_category']);
            $audioFile = "{$this->tempDir}/audio.mp3";
            if ($audio && downloadAudio($audio['url'], $audioFile)) {
                if (applyBackgroundAudio($workingVideo, $audioFile, $output, $this->options['audio_volume'])) {
                    $workingVideo = $output;
                    $steps[] = 'audio';
                }
            }
        }

        if ($workingVideo !== $this->outputPath) {
            copy($workingVideo, $this->outputPath);
        }

        $this->cleanup();
        return [
            'success' => true,
            'output_file' => $this->outputPath,
            'steps' => $steps
        ];
    }

    private function cleanup() {
        if (!ENABLE_DEBUG && file_exists($this->tempDir)) {
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
