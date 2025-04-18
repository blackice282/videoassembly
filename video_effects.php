<?php
// video_effects.php - Gestisce gli effetti video

/**
 * Catalogo di effetti video disponibili
 */
function getVideoEffects() {
    return [
        'vintage' => [
            'name' => 'Effetto Vintage/Retrò',
            'description' => 'Dà al video un aspetto nostalgico anni \'70-\'80',
            'filter' => 'curves=r=0.2:g=0.4:b=0.6:master=0.5,colorbalance=rs=-0.075:bs=0.1,unsharp=3:3:1,hue=s=0.5:h=0,vignette=0.4:0.4'
        ],
        'bianco_nero' => [
            'name' => 'Bianco e Nero',
            'description' => 'Converte il video in scala di grigi con contrasto migliorato',
            'filter' => 'colorchannelmixer=.3:.4:.3:0:.3:.4:.3:0:.3:.4:.3,eq=contrast=1.1'
        ],
        'caldo' => [
            'name' => 'Toni Caldi',
            'description' => 'Aumenta i toni caldi (rosso, arancione) del video',
            'filter' => 'colorbalance=rs=0.1:gs=0.05:bs=-0.1:rm=0.05:gm=-0.05:bm=-0.1'
        ],
        'freddo' => [
            'name' => 'Toni Freddi',
            'description' => 'Aumenta i toni freddi (blu) del video',
            'filter' => 'colorbalance=rs=-0.08:gs=-0.02:bs=0.08:rm=-0.05:gm=-0.02:bm=0.05'
        ],
        'dream' => [
            'name' => 'Effetto Sogno',
            'description' => 'Crea un effetto etereo/sognante con leggero bloom',
            'filter' => 'curves=master=0.6,gblur=sigma=0.5:steps=1,eq=brightness=0.05:contrast=1.1,unsharp'
        ],
        'cinema' => [
            'name' => 'Cinema',
            'description' => 'Effetto cinematografico professionale con barre nere',
            'filter' => 'colorbalance=rs=0.05:gs=0.01:bs=-0.05,unsharp=5:5:1,eq=contrast=1.2:saturation=1.15,crop=ih/2.35:ih'
        ],
        'hdr' => [
            'name' => 'Effetto HDR',
            'description' => 'Simula un effetto HDR con colori vivaci e contrasto elevato',
            'filter' => 'curves=master=0:0 0.25:0.15 0.5:0.5 0.75:0.85 1:1,eq=contrast=1.2:saturation=1.4,unsharp'
        ],
        'brillante' => [
            'name' => 'Colori Brillanti',
            'description' => 'Aumenta la saturazione e la luminosità dei colori',
            'filter' => 'eq=contrast=1.1:brightness=0.1:saturation=1.5,unsharp'
        ],
        'instagram' => [
            'name' => 'Instagram Style',
            'description' => 'Effetto ispirato ai filtri social media',
            'filter' => 'colorbalance=rs=0.08:gs=0.04:bs=-0.02,eq=brightness=0.05:contrast=1.2:saturation=1.4,vignette=0.3:0.3'
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
    $effects = getVideoEffects();
    
    if (!isset($effects[$effectName])) {
        return false;
    }
    
    $effect = $effects[$effectName];
    $filter = $effect['filter'];
    
    // Applica il filtro video mantenendo l'audio originale
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
           " -vf \"{$filter}\" -c:a copy " . 
           escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0 && file_exists($outputPath);
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
    
    // Ottieni la durata del primo video
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath1);
    $duration1 = floatval(trim(shell_exec($cmd)));
    
    // Calcola il punto di inizio della transizione
    $transitionStart = max(0, $duration1 - $duration);
    
    // Applica la transizione
    $transitions = [
        'fade' => "xfade=transition=fade:duration=$duration:offset=$transitionStart",
        'wipeleft' => "xfade=transition=wipeleft:duration=$duration:offset=$transitionStart",
        'wiperight' => "xfade=transition=wiperight:duration=$duration:offset=$transitionStart",
        'wipeup' => "xfade=transition=wipeup:duration=$duration:offset=$transitionStart",
        'wipedown' => "xfade=transition=wipedown:duration=$duration:offset=$transitionStart",
        'slideleft' => "xfade=transition=slideleft:duration=$duration:offset=$transitionStart",
        'slideright' => "xfade=transition=slideright:duration=$duration:offset=$transitionStart",
        'dissolve' => "xfade=transition=dissolve:duration=$duration:offset=$transitionStart",
        'circleopen' => "xfade=transition=circleopen:duration=$duration:offset=$transitionStart",
        'circleclose' => "xfade=transition=circleclose:duration=$duration:offset=$transitionStart"
    ];
    
    $transitionFilter = isset($transitions[$transition]) ? $transitions[$transition] : $transitions['fade'];
    
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . 
           " -filter_complex \"$transitionFilter\" " . 
           escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    // Rimuovi il file di lista
    if (file_exists($listFile)) {
        unlink($listFile);
    }
    
    return $returnCode === 0 && file_exists($outputPath);
}

/**
 * Aggiungi testo a un video
 * 
 * @param string $videoPath Percorso del video
 * @param string $outputPath Percorso del video di output
 * @param string $text Testo da aggiungere
 * @param string $position Posizione del testo (bottom, top, center)
 * @param string $fontColor Colore del font
 * @return bool Successo dell'operazione
 */
function addTextToVideo($videoPath, $outputPath, $text, $position = 'bottom', $fontColor = 'white') {
    // Definisci le posizioni
    $positions = [
        'bottom' => "x=(w-text_w)/2:y=h-text_h-20",
        'top' => "x=(w-text_w)/2:y=20",
        'center' => "x=(w-text_w)/2:y=(h-text_h)/2"
    ];
    
    $posStr = isset($positions[$position]) ? $positions[$position] : $positions['bottom'];
    
    // Aggiungi il testo
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
           " -vf \"drawtext=fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:text='" . str_replace("'", "\\'", $text) . 
           "':fontcolor=$fontColor:fontsize=24:$posStr:shadowcolor=black:shadowx=2:shadowy=2\" " . 
           " -c:a copy " . escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0 && file_exists($outputPath);
}

/**
 * Aggiungi un filtro overlay per decorare il video (cornici, etc.)
 * 
 * @param string $videoPath Percorso del video
 * @param string $outputPath Percorso del video di output
 * @param string $overlayType Tipo di overlay
 * @return bool Successo dell'operazione
 */
function addOverlayEffect($videoPath, $outputPath, $overlayType = 'border') {
    $filter = '';
    
    switch ($overlayType) {
        case 'border':
            // Aggiunge un bordo bianco attorno al video
            $filter = "pad=width=1.05*iw:height=1.05*ih:x=(ow-iw)/2:y=(oh-ih)/2:color=white";
            break;
        case 'rounded':
            // Crea un effetto di angoli arrotondati (richiede libreria FFmpeg con supporto filtri avanzati)
            $filter = "format=rgba,geq=r='r(X,Y)':a='if(gt(X,25)*lt(X,W-25)*gt(Y,25)*lt(Y,H-25)+lt(sqrt((X-25)^2+(Y-25)^2),25)*lt(X,25)*lt(Y,25)+lt(sqrt((X-(W-25))^2+(Y-25)^2),25)*gt(X,W-25)*lt(Y,25)+lt(sqrt((X-25)^2+(Y-(H-25))^2),25)*lt(X,25)*gt(Y,H-25)+lt(sqrt((X-(W-25))^2+(Y-(H-25))^2),25)*gt(X,W-25)*gt(Y,H-25),255,0)'";
            break;
        case 'vignette':
            // Aggiunge un effetto vignettatura
            $filter = "vignette=angle=PI/4";
            break;
        case 'mirror':
            // Crea un effetto specchio sulla metà destra
            $filter = "crop=iw/2:ih:0:0,split[left][tmp];[tmp]hflip[right];[left][right] hstack";
            break;
        default:
            return false;
    }
    
    // Applica il filtro
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
           " -vf \"$filter\" -c:a copy " . 
           escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0 && file_exists($outputPath);
}
?>
