<?php
// video_processor.php - Gestione centralizzata degli effetti video e delle trasformazioni

require_once 'config.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';
require_once 'debug_utility.php';

/**
 * Classe per gestire l'elaborazione completa dei video
 */
class VideoProcessor {
    private $inputVideo;
    private $outputPath;
    private $tempDir;
    private $options;
    private $videoInfo;
    
    /**
     * Costruttore
     * 
     * @param string $inputVideo Percorso del video di input
     * @param string $outputPath Percorso del video di output (opzionale)
     * @param array $options Opzioni di elaborazione
     */
    public function __construct($inputVideo, $outputPath = null, $options = []) {
        $this->inputVideo = $inputVideo;
        
        // Se non è specificato un percorso di output, generane uno
        if ($outputPath === null) {
            $this->outputPath = generateUniqueFilePath('processed', 'mp4', getConfig('paths.uploads', 'uploads'));
        } else {
            $this->outputPath = $outputPath;
        }
        
        // Directory temporanea per i file intermedi
        $this->tempDir = getConfig('paths.temp', 'temp') . '/proc_' . uniqid();
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        
        // Opzioni predefinite
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
        
        // Analizza il video di input
        $this->videoInfo = analyzeVideo($inputVideo);
        
        debugLog("VideoProcessor inizializzato: {$this->inputVideo} -> {$this->outputPath}", "info", "processor");
    }
    
    /**
     * Elabora il video applicando tutti gli effetti selezionati
     * 
     * @return array Risultato dell'elaborazione
     */
    public function processVideo() {
        // Verifica se il video di input è valido
        if (!$this->videoInfo['success']) {
            debugLog("Video di input non valido: {$this->inputVideo}", "error", "processor");
            return [
                'success' => false,
                'message' => 'Video di input non valido',
                'details' => $this->videoInfo
            ];
        }
        
        // Video di lavoro (inizialmente è l'input)
        $workingVideo = $this->inputVideo;
        $steps = [];
        
        // Applica privacy dei volti
        if ($this->options['apply_face_privacy']) {
            $privacyResult = $this->applyFacePrivacy($workingVideo);
            $steps[] = $privacyResult;
            
            if ($privacyResult['success']) {
                $workingVideo = $privacyResult['output_file'];
            }
        }
        
        // Applica effetti video
        if ($this->options['apply_effect'] && $this->options['effect_name'] !== 'none') {
            $effectResult = $this->applyVideoEffect($workingVideo);
            $steps[] = $effectResult;
            
            if ($effectResult['success']) {
                $workingVideo = $effectResult['output_file'];
            }
        }
        
        // Applica audio di sottofondo
        if ($this->options['apply_audio'] && $this->options['audio_category'] !== 'none') {
            $audioResult = $this->applyBackgroundAudio($workingVideo);
            $steps[] = $audioResult;
            
            if ($audioResult['success']) {
                $workingVideo = $audioResult['output_file'];
            }
        }
        
        // Se non è stata eseguita alcuna modifica, copia semplicemente il file originale
        if ($workingVideo === $this->inputVideo) {
            debugLog("Nessuna modifica applicata, copia il file originale", "info", "processor");
            if (copy($this->inputVideo, $this->outputPath)) {
                return [
                    'success' => true,
                    'message' => 'File copiato senza modifiche',
                    'output_file' => $this->outputPath,
                    'steps' => $steps
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Errore nella copia del file',
                    'steps' => $steps
                ];
            }
        }
        
        // Se il file finale non è già nell'output path, copialo
        if ($workingVideo !== $this->outputPath) {
            if (!copy($workingVideo, $this->outputPath)) {
                debugLog("Errore nella copia del file finale: $workingVideo -> {$this->outputPath}", "error", "processor");
                return [
                    'success' => false,
                    'message' => 'Errore nella copia del file finale',
                    'steps' => $steps
                ];
            }
        }
        
        // Pulisci i file temporanei
        $this->cleanup();
        
        return [
            'success' => true,
            'message' => 'Elaborazione completata con successo',
            'output_file' => $this->outputPath,
            'steps' => $steps
        ];
    }
    
    /**
     * Applica la privacy dei volti
     */
    private function applyFacePrivacy($videoPath) {
        debugLog("Applicazione privacy volti: $videoPath", "info", "processor-face");
        
        $outputFile = "{$this->tempDir}/privacy_" . basename($videoPath);
        $startTime = microtime(true);
        
        $result = applyFacePrivacy($videoPath, $outputFile, $this->options['exclude_yellow_vests']);
        $executionTime = round(microtime(true) - $startTime, 2);
        
        if ($result && file_exists($outputFile) && filesize($outputFile) > 0) {
            debugLog("Privacy volti applicata con successo: $outputFile, Tempo: {$executionTime}s", "info", "processor-face");
            return [
                'success' => true,
                'message' => 'Privacy volti applicata con successo',
                'output_file' => $outputFile,
                'execution_time' => $executionTime
            ];
        } else {
            debugLog("Errore nell'applicazione della privacy volti, Tempo: {$executionTime}s", "error", "processor-face");
            return [
                'success' => false,
                'message' => 'Errore nell\'applicazione della privacy volti',
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * Pulisce i file temporanei
     */
    private function cleanup() {
        if (!getConfig('system.cleanup_temp', true)) {
            return;
        }
        
        debugLog("Pulizia dei file temporanei: {$this->tempDir}", "info", "processor-cleanup");
        
        // Elimina ricorsivamente tutti i file nella directory temporanea
        if (file_exists($this->tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $fileinfo) {
                if ($fileinfo->isDir()) {
                    rmdir($fileinfo->getRealPath());
                } else {
                    unlink($fileinfo->getRealPath());
                }
            }
            
            rmdir($this->tempDir);
            debugLog("Directory temporanea rimossa: {$this->tempDir}", "info", "processor-cleanup");
        }
    }
    
    /**
     * Distruttore - assicura la pulizia anche in caso di errori
     */
    public function __destruct() {
        $this->cleanup();
    }
}

/**
 * Funzione helper per processare un video con tutte le opzioni
 * 
 * @param string $inputVideo Percorso del video di input
 * @param string $outputPath Percorso del video di output (opzionale)
 * @param array $options Opzioni di elaborazione
 * @return array Risultato dell'elaborazione
 */
function processVideoWithOptions($inputVideo, $outputPath = null, $options = []) {
    $processor = new VideoProcessor($inputVideo, $outputPath, $options);
    return $processor->processVideo();
}

    
    /**
     * Applica effetti video
     */
    private function applyVideoEffect($videoPath) {
        debugLog("Applicazione effetto video: {$this->options['effect_name']} su $videoPath", "info", "processor-effect");
        
        $outputFile = "{$this->tempDir}/effect_" . basename($videoPath);
        $startTime = microtime(true);
        
        $result = applyVideoEffect($videoPath, $outputFile, $this->options['effect_name']);
        $executionTime = round(microtime(true) - $startTime, 2);
        
        if ($result && file_exists($outputFile) && filesize($outputFile) > 0) {
            debugLog("Effetto video applicato con successo: $outputFile, Tempo: {$executionTime}s", "info", "processor-effect");
            return [
                'success' => true,
                'message' => 'Effetto video applicato con successo',
                'effect_name' => $this->options['effect_name'],
                'output_file' => $outputFile,
                'execution_time' => $executionTime
            ];
        } else {
            debugLog("Errore nell'applicazione dell'effetto video, Tempo: {$executionTime}s", "error", "processor-effect");
            return [
                'success' => false,
                'message' => 'Errore nell\'applicazione dell\'effetto video',
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * Applica audio di sottofondo
     * 
     * @param string $videoPath Percorso del video di input
     * @return array Risultato dell'operazione
     */
    private function applyBackgroundAudio($videoPath) {
        debugLog("Applicazione audio di sottofondo: {$this->options['audio_category']} su $videoPath", "info", "processor-audio");
        
        // Ottieni un audio dalla categoria selezionata
        $audio = getRandomAudioFromCategory($this->options['audio_category']);
        if (!$audio) {
            debugLog("Nessun audio disponibile nella categoria: {$this->options['audio_category']}", "error", "processor-audio");
            return [
                'success' => false,
                'message' => 'Nessun audio disponibile nella categoria selezionata'
            ];
        }
        
        // Scarica l'audio
        $audioFile = "{$this->tempDir}/audio_" . uniqid() . '.mp3';
        $downloadResult = downloadAudio($audio['url'], $audioFile);
        
        if (!$downloadResult || !file_exists($audioFile) || filesize($audioFile) <= 0) {
            debugLog("Errore nel download dell'audio: {$audio['url']}", "error", "processor-audio");
            return [
                'success' => false,
                'message' => 'Errore nel download dell\'audio'
            ];
        }
        
        // Applica l'audio al video
        $outputFile = "{$this->tempDir}/audio_" . basename($videoPath);
        $startTime = microtime(true);
        
        $result = applyBackgroundAudio($videoPath, $audioFile, $outputFile, $this->options['audio_volume']);
        $executionTime = round(microtime(true) - $startTime, 2);
        
        if ($result && file_exists($outputFile) && filesize($outputFile) > 0) {
            debugLog("Audio di sottofondo applicato con successo: $outputFile, Tempo: {$executionTime}s", "info", "processor-audio");
            return [
                'success' => true,
                'message' => 'Audio di sottofondo applicato con successo',
                'audio_name' => $audio['name'],
                'output_file' => $outputFile,
                'execution_time' => $executionTime
            ];
        } else {
            debugLog("Errore nell'applicazione dell'audio di sottofondo, Tempo: {$executionTime}s", "error", "processor-audio");
            return [
                'success' => false,
                'message' => 'Errore nell\'applicazione dell\'audio di sottofondo',
                'execution_time' => $executionTime
            ];
        }
    }
