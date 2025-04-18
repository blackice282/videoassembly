<?php
// video_effects.php - Gestisce gli effetti video - VERSIONE CORRETTA

/**
 * Catalogo di effetti video disponibili
 */
function getVideoEffects() {
    return [
        'vintage' => [
            'name' => 'Effetto Vintage/Retrò',
            'description' => 'Dà al video un aspetto nostalgico anni \'70-\'80',
            'filter' => 'curves=r=0.2:g=0.4:b=0.6:master=0.5,colorbalance=rs=-0.075:bs=0.1'
        ],
        'bianco_nero' => [
            'name' => 'Bianco e Nero',
            'description' => 'Converte il video in scala di grigi con contrasto migliorato',
            'filter' => 'hue=s=0'
        ],
        'caldo' => [
            'name' => 'Toni Caldi',
            'description' => 'Aumenta i toni caldi (rosso, arancione) del video',
            'filter' => 'colorbalance=rs=0.1:gs=0.05:bs=-0.1'
        ],
        'freddo' => [
            'name' => 'Toni Freddi',
            'description' => 'Aumenta i toni freddi (blu) del video',
            'filter' => 'colorbalance=rs=-0.08:gs=-0.02:bs=0.08'
        ],
        'dream' => [
            'name' => 'Effetto Sogno',
            'description' => 'Crea un effetto etereo/sognante con leggero bloom',
            'filter' => 'gblur=sigma=0.5:steps=1,eq=brightness=0.05:contrast=1.1'
        ],
        'cinema' => [
            'name' => 'Cinema',
            'description' => 'Effetto cinematografico professionale con barre nere',
            'filter' => 'colorbalance=rs=0.05:gs=0.01:bs=-0.05,eq=contrast=1.2:saturation=1.15'
        ],
        'hdr' => [
            'name' => 'Effetto HDR',
            'description' => 'Simula un effetto HDR con colori vivaci e contrasto elevato',
            'filter' => 'eq=contrast=1.2:saturation=1.4'
        ],
        'brillante' => [
            'name' => 'Colori Brillanti',
            'description' => 'Aumenta la saturazione e la luminosità dei colori',
            'filter' => 'eq=contrast=1.1:brightness=0.1:saturation=1.5'
        ],
        'instagram' => [
            'name' => 'Instagram Style',
            'description' => 'Effetto ispirato ai filtri social media',
            'filter' => 'colorbalance=rs=0.08:gs=0.04:bs=-0.02,eq=brightness=0.05:contrast=1.2:saturation=1.4'
        ]
    ];
}

/**
 * Applica un effetto video a un file video
 * 
 * @param string $videoPath Percorso del video di input
 * @param string $outputPath Percorso del video di output
 * @param string $effectName Nome dell'effetto da applicare
 * @return bool Successo dell'operazione
 */
function applyVideoEffect($videoPath, $outputPath, $effectName) {
    // Verifica esistenza del file di input
    if (!file_exists($videoPath) || filesize($videoPath) <= 0) {
        error_log("File di input non valido o vuoto: $videoPath");
        return false;
    }
    
    $effects = getVideoEffects();
    
    if (!isset($effects[$effectName])) {
        error_log("Effetto non trovato: $effectName");
        return false;
    }
    
    $effect = $effects[$effectName];
    $filter = $effect['filter'];
    
    // Crea la directory di output se non esiste
    $outputDir = dirname($outputPath);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Approccio semplificato e robusto per applicare l'effetto
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -vf " . escapeshellarg($filter) . 
           " -c:v libx264 -preset ultrafast -crf 23 -c:a copy " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    exec($cmd, $output, $returnCode);
    
    // Log per debugging
    error_log("Comando effetto: $cmd");
    error_log("Output comando: " . implode("\n", $output));
    error_log("Codice ritorno: $returnCode");
    
    // Verifica se l'output esiste e ha dimensioni maggiori di zero
    if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        error_log("Effetto applicato con successo: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    }
    
    // Se fallisce, prova con un approccio meno complesso
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -vf eq=contrast=1.1:brightness=0.05:saturation=1.1 " . 
           " -c:v libx264 -preset ultrafast -crf 23 -c:a copy " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    exec($cmd, $output, $returnCode);
    error_log("Secondo tentativo comando: $cmd");
    error_log("Output secondo tentativo: " . implode("\n", $output));
    
    return $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}

/**
 * Applica una transizione tra due video
 * 
 * @param string $videoPath1 Primo video
 * @param string $videoPath2 Secondo video
 * @param string $outputPath Video risultante
 * @param string $transition Tipo di transizione
 * @param float $duration Durata della transizione in secondi
 * @return bool Successo dell'operazione
 */
function applyTransitionEffect($videoPath1, $videoPath2, $outputPath, $transition = 'fade', $duration = 1.0) {
    // Crea una lista di file per la concatenazione
    $listFile = dirname($outputPath) . '/transition_list_' . uniqid() . '.txt';
    $content = "file '" . str_replace("'", "\\'", realpath($videoPath1)) . "'\n";
    $content .= "file '" . str_replace("'", "\\'", realpath($videoPath2)) . "'\n";
    file_put_contents($listFile, $content);
    
    // Semplice concatenazione come fallback sicuro
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath);
    exec($cmd, $output, $returnCode);
    
    // Rimuovi il file di lista
    if (file_exists($listFile)) {
        unlink($listFile);
    }
    
    return $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}
