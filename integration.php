<?php
// integration.php - Script per integrare tutte le funzionalità

// Includi tutti i file necessari
require_once 'compatibility.php';
require_once 'config.php';
require_once 'debug_utility.php';
require_once 'video_processor.php';
require_once 'improved_face_privacy.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'transitions.php';
require_once 'duration_editor.php';

// Inizializza l'ambiente
function initializeEnvironment() {
    // Crea le directory necessarie
    $dirs = [
        getConfig('paths.uploads', 'uploads'),
        getConfig('paths.temp', 'temp'),
        getConfig('paths.output', 'output'),
        'logs'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            debugLog("Directory creata: $dir", "info", "init");
        }
    }
    
    // Verifica le dipendenze
    $deps = checkAllDependencies();
    debugLog("Verifica dipendenze: " . json_encode($deps), "info", "init");
    
    // Imposta il limite di tempo per operazioni lunghe
    set_time_limit(300); // 5 minuti
    
    // Imposta il percorso base per l'app
    if (!isset($_SERVER['BASE_PATH'])) {
        $_SERVER['BASE_PATH'] = dirname($_SERVER['SCRIPT_NAME']);
    }
    
    return $deps;
}

// Mostra un messaggio di errore HTML formattato
function showErrorMessage($title, $message) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; color: #721c24;'>";
    echo "<strong>⚠️ $title</strong><br>";
    echo $message;
    echo "</div>";
}

// Mostra un messaggio di successo HTML formattato
function showSuccessMessage($title, $message) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;'>";
    echo "<strong>✅ $title</strong><br>";
    echo $message;
    echo "</div>";
}

// Mostra un messaggio informativo HTML formattato
function showInfoMessage($title, $message) {
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; color: #0c5460;'>";
    echo "<strong>ℹ️ $title</strong><br>";
    echo $message;
    echo "</div>";
}

// Genera una miniatura HTML per il video
function generateVideoThumbnail($videoPath, $width = 200) {
    $timestamp = date('Ymd_His');
    $thumbnailPath = getConfig('paths.uploads', 'uploads') . '/thumbnail_' . $timestamp . '.jpg';
    
    // Genera la miniatura
    $thumbnailCmd = "ffmpeg -ss 00:00:03 -i " . escapeshellarg($videoPath) . " -vframes 1 -q:v 2 " . escapeshellarg($thumbnailPath);
    shell_exec($thumbnailCmd);
    
    // Verifica se la miniatura è stata creata
    if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
        $html = "<img src='$thumbnailPath' style='max-width: {$width}px; max-height: " . ($width * 0.6) . "px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);'>";
        return $html;
    }
    
    return '';
}

// Ottieni le informazioni HTML sul video
function getVideoInfoHtml($videoPath) {
    $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
    $videoInfo = shell_exec($cmd);
    
    if ($videoInfo) {
        $infoLines = explode("\n", $videoInfo);
        if (count($infoLines) >= 3) {
            $width = $infoLines[0];
            $height = $infoLines[1];
            $duration = round(floatval($infoLines[2]), 1);
            
            $html = "<div style='margin-top: 15px; font-size: 14px; color: #666;'>";
            $html .= "ℹ️ <strong>Informazioni video:</strong> ";
            $html .= "{$width}x{$height} | ";
            $html .= "Durata: " . gmdate("H:i:s", $duration);
            $html .= "</div>";
            
            return $html;
        }
    }
    
    return '';
}

// Genera un pulsante di download HTML per il video
function generateDownloadButton($videoPath) {
    $html = "<a href='$videoPath' download style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold;'>";
    $html .= "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' style='vertical-align: text-bottom; margin-right: 5px;' viewBox='0 0 16 16'>";
    $html .= "<path d='M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z'/>";
    $html .= "<path d='M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z'/>";
    $html .= "</svg>";
    $html .= "Scarica il video</a>";
    
    return $html;
}

// Elabora un video con tutte le opzioni specificate
function processVideoWithAllOptions($inputVideo, $options = []) {
    // Verifica che il video esista
    if (!file_exists($inputVideo)) {
        return [
            'success' => false,
            'message' => 'File non trovato'
        ];
    }
    
    debugLog("Elaborazione video: $inputVideo con opzioni: " . json_encode($options), "info", "process");
    
    // Percorso di output
    $outputPath = isset($options['output_path']) ? $options['output_path'] : 
                 generateUniqueFilePath('processed', 'mp4', getConfig('paths.uploads', 'uploads'));
    
    // Usa la classe VideoProcessor
    $processor = new VideoProcessor($inputVideo, $outputPath, $options);
    $result = $processor->processVideo();
    
    debugLog("Elaborazione completata: " . json_encode($result), "info", "process");
    
    return $result;
}

// Ottiene l'elenco degli effetti video disponibili per il form
function getVideoEffectsForForm() {
    $effects = getVideoEffects();
    $options = [];
    
    foreach ($effects as $id => $effect) {
        $options[$id] = $effect['name'];
    }
    
    return $options;
}

// Ottiene l'elenco delle categorie audio disponibili per il form
function getAudioCategoriesForForm() {
    $catalog = getAudioCatalog();
    $options = [];
    
    foreach ($catalog as $category => $audios) {
        // Converti la chiave in un nome presentabile
        $displayName = ucfirst(str_replace('_', ' ', $category));
        $options[$category] = $displayName;
    }
    
    return $options;
}

// Genera il codice per la sezione delle opzioni di privacy dei volti
function generateFacePrivacyOptions() {
    $html = '<div class="option-group">';
    $html .= '<h3>Privacy e protezione:</h3>';
    $html .= '<div class="feature-toggle">';
    $html .= '<label>';
    $html .= '<input type="checkbox" name="apply_face_privacy" value="1">'; 
    $html .= 'Applica emoji sui volti (protezione privacy)';
    $html .= '</label>';
    $html .= '</div>';
    $html .= '<div id="privacyOptions" style="margin-left: 20px; margin-top: 5px; display: none;">';
    $html .= '<label>';
    $html .= '<input type="checkbox" name="exclude_yellow_vests" value="1" checked>'; 
    $html .= 'Escludi operatori con pettorine gialle';
    $html .= '</label>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Genera il codice per la sezione degli effetti video
function generateVideoEffectsOptions() {
    $effects = getVideoEffectsForForm();
    
    $html = '<div class="option-group">';
    $html .= '<h3>Effetti video:</h3>';
    $html .= '<div class="feature-toggle">';
    $html .= '<label>';
    $html .= '<input type="checkbox" name="apply_effect" value="1">'; 
    $html .= 'Applica un effetto al video';
    $html .= '</label>';
    $html .= '</div>';
    $html .= '<div id="effectOptions" style="margin-left: 20px; margin-top: 5px; display: none;">';
    $html .= '<select name="video_effect" id="videoEffectSelect">';
    $html .= '<option value="none">Nessun effetto</option>';
    
    foreach ($effects as $id => $name) {
        $html .= '<option value="' . $id . '">' . $name . '</option>';
    }
    
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Genera il codice per la sezione dell'audio di sottofondo
function generateAudioOptions() {
    $categories = getAudioCategoriesForForm();
    
    $html = '<div class="option-group">';
    $html .= '<h3>Audio di sottofondo:</h3>';
    $html .= '<div class="feature-toggle">';
    $html .= '<label>';
    $html .= '<input type="checkbox" name="apply_audio" value="1">'; 
    $html .= 'Aggiungi musica di sottofondo';
    $html .= '</label>';
    $html .= '</div>';
    $html .= '<div id="audioOptions" style="margin-left: 20px; margin-top: 5px; display: none;">';
    $html .= '<div style="margin-bottom: 10px;">';
    $html .= '<label for="audioCategorySelect">Categoria musicale:</label>';
    $html .= '<select name="audio_category" id="audioCategorySelect">';
    $html .= '<option value="none">Nessuna musica</option>';
    
    foreach ($categories as $id => $name) {
        $html .= '<option value="' . $id . '">' . $name . '</option>';
    }
    
    $html .= '</select>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<label for="audioVolumeRange">Volume musica:</label>';
    $html .= '<input type="range" id="audioVolumeRange" name="audio_volume" min="10" max="70" value="30" class="range-slider">';
    $html .= '<span id="audioVolumeValue">30%</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Funzione per generare il JavaScript necessario per gestire le opzioni
function generateOptionsJavascript() {
    $js = <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestione opzioni durata
    const durationSelect = document.getElementById('durationSelect');
    const durationMethodOptions = document.getElementById('durationMethodOptions');
    
    function toggleDurationOptions() {
        if (durationSelect && durationMethodOptions) {
            durationMethodOptions.style.display = durationSelect.value === '0' ? 'none' : 'block';
        }
    }
    
    // Inizializza lo stato al caricamento
    toggleDurationOptions();
    if (durationSelect) {
        durationSelect.addEventListener('change', toggleDurationOptions);
    }
    
    // Gestione opzioni privacy volti
    const applyFacePrivacy = document.querySelector('input[name="apply_face_privacy"]');
    const privacyOptions = document.getElementById('privacyOptions');
    
    function togglePrivacyOptions() {
        if (applyFacePrivacy && privacyOptions) {
            privacyOptions.style.display = applyFacePrivacy.checked ? 'block' : 'none';
        }
    }
    
    if (applyFacePrivacy) {
        togglePrivacyOptions();
        applyFacePrivacy.addEventListener('change', togglePrivacyOptions);
    }
    
    // Gestione opzioni effetti video
    const applyEffect = document.querySelector('input[name="apply_effect"]');
    const effectOptions = document.getElementById('effectOptions');
    
    function toggleEffectOptions() {
        if (applyEffect && effectOptions) {
            effectOptions.style.display = applyEffect.checked ? 'block' : 'none';
        }
    }
    
    if (applyEffect) {
        toggleEffectOptions();
        applyEffect.addEventListener('change', toggleEffectOptions);
    }
    
    // Gestione opzioni audio di sottofondo
    const applyAudio = document.querySelector('input[name="apply_audio"]');
    const audioOptions = document.getElementById('audioOptions');
    const audioVolumeRange = document.getElementById('audioVolumeRange');
    const audioVolumeValue = document.getElementById('audioVolumeValue');
    
    function toggleAudioOptions() {
        if (applyAudio && audioOptions) {
            audioOptions.style.display = applyAudio.checked ? 'block' : 'none';
        }
    }
    
    function updateAudioVolumeValue() {
        if (audioVolumeRange && audioVolumeValue) {
            audioVolumeValue.textContent = audioVolumeRange.value + '%';
        }
    }
    
    if (applyAudio) {
        toggleAudioOptions();
        applyAudio.addEventListener('change', toggleAudioOptions);
    }
    
    if (audioVolumeRange) {
        updateAudioVolumeValue();
        audioVolumeRange.addEventListener('input', updateAudioVolumeValue);
    }
});
</script>
EOT;
    
    return $js;
}
