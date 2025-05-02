<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ffmpeg_script.php';
require_once __DIR__ . '/people_detection.php';
require_once __DIR__ . '/duration_editor.php';
require_once __DIR__ . '/video_effects.php';
require_once __DIR__ . '/audio_manager.php';
require_once __DIR__ . '/face_detection.php';
require_once __DIR__ . '/privacy_manager.php';

// Genera un job ID e inizializza il log
$job     = uniqid();
$logFile = getConfig('paths.temp') . "/progress_{$job}.log";
file_put_contents($logFile, "[START {$job}] " . date('c') . "\n");

function logStep($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['videos'])) {
    // 1) Crea le cartelle uploads/ e temp/ se mancanti
    foreach ([getConfig('paths.uploads'), getConfig('paths.temp')] as $d) {
        if (!file_exists($d)) mkdir($d, 0777, true);
    }
    set_time_limit(0);

    // 2) Sposta gli upload nella cartella uploads/
    $clips = [];
    foreach ($_FILES['videos']['tmp_name'] as $i => $tmp) {
        if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
            $name = uniqid('vid_') . '_' . basename($_FILES['videos']['name'][$i]);
            $dest = getConfig('paths.uploads') . '/' . $name;
            move_uploaded_file($tmp, $dest);
            trackFile($dest, $name, 'upload');
            logStep("Uploaded $name");
            $clips[] = $dest;
        }
    }

    // 3) Recupera le opzioni dal form
    $opts = [
        'mode'            => $_POST['mode']            ?? 'simple',
        'duration'        => intval($_POST['duration']) * 60,
        'duration_method' => $_POST['duration_method'] ?? 'trim',
        'effect'          => $_POST['effect']          ?? 'none',
        'audio'           => $_POST['audio']           ?? 'none',
        'privacy'         => isset($_POST['privacy']),
    ];

    // 4) Applica le transizioni (concat)
    $outputName = 'render_' . date('Ymd_His') . '.mp4';
    $outputPath = getConfig('paths.uploads') . '/' . $outputName;
    logStep("Applying transitions");
    applyTransitions($clips, $outputPath);
    $out = $outputPath;

    // 5) Regola la durata
    if ($opts['duration'] > 0) {
        logStep("Editing duration ({$opts['duration_method']})");
        $out = applyDurationEdit($out, $opts['duration'], $opts['duration_method']);
    }

    // 6) Applica l’effetto video
    if ($opts['effect'] !== 'none') {
        logStep("Applying video effect ({$opts['effect']})");
        $tmp = getConfig('paths.temp') . '/eff_' . uniqid() . '.mp4';
        applyVideoEffect($out, $tmp, $opts['effect']);
        $out = $tmp;
    }

    // 7) Applica privacy volti
    if ($opts['privacy']) {
        logStep("Applying face privacy");
        $tmp = getConfig('paths.temp') . '/priv_' . uniqid() . '.mp4';
        applyFacePrivacy($out, $tmp);
        $out = $tmp;
    }

    // 8) Aggiunge audio di sottofondo
    if ($opts['audio'] !== 'none') {
        logStep("Applying background audio ({$opts['audio']})");
        $audio = getRandomAudioFromCategory($opts['audio']);
        if ($audio) {
            $tmpA = getConfig('paths.temp') . '/aud_' . uniqid() . '.mp3';
            downloadAudio($audio['url'], $tmpA);
            $tmpV = getConfig('paths.temp') . '/audvid_' . uniqid() . '.mp4';
            applyBackgroundAudio($out, $tmpA, $tmpV, 0.3);
            $out = $tmpV;
        }
    }

    trackFile($out, basename($out), 'output');
    logStep("JOB COMPLETE, output: " . basename($out));

    // 9) Mostra log live e link di download
    echo "<div style='padding:2rem;font-family:sans-serif;'>";
    echo "<h2>🚀 Elaborazione in corso…</h2>";
    echo "<pre id='log' style='background:#eee;padding:1rem;height:200px;overflow:auto;'></pre>";
    echo "<script>
        const job='{$job}';
        async function updateLog() {
            let txt = await fetch('status.php?job='+job).then(r=>r.text());
            document.getElementById('log').innerText = txt;
        }
        setInterval(updateLog, 1000);
        updateLog();
    </script>";
    echo "<p style='margin-top:1rem;'>
            Quando vedi 'JOB COMPLETE', <a href='uploads/{$outputName}' 
            style='display:inline-block;padding:1rem 2rem;
                   background:#28a745;color:#fff;text-decoration:none;
                   border-radius:6px;'>Scarica il video</a>
          </p></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>VideoAssembly</title>
  <style>
    body{background:#f4f4f4;margin:0;font-family:sans-serif;padding:2rem;}
    form{max-width:600px;margin:auto;background:#fff;padding:2rem;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,.1);}
    label{display:block;margin-top:1rem;}
    input,select{width:100%;padding:.5rem;margin-top:.3rem;}
    button{margin-top:1.5rem;width:100%;padding:.8rem;background:#007bff;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:1rem;}
    button:hover{background:#0056b3;}
  </style>
</head>
<body>
  <h1 style="text-align:center;">VideoAssembly</h1>
  <form method="post" enctype="multipart/form-data">
    <label>Carica uno o più video:<br><input type="file" name="videos[]" multiple required></label>
    <label>Modalità:<br>
      <select name="mode">
        <option value="simple">Semplice</option>
        <option value="detect_people">Rileva Persone</option>
      </select>
    </label>
    <label>Durata (minuti):<br><input type="number" name="duration" value="3" min="1"></label>
    <label>Metodo durata:<br>
      <select name="duration_method">
        <option value="trim">Taglia</option>
        <option value="speed">Accelera</option>
      </select>
    </label>
    <label>Effetto video:<br>
      <select name="effect">
        <option value="none">Nessuno</option>
        <option value="bw">Bianco e Nero</option>
        <option value="vintage">Vintage</option>
        <option value="contrast">Contrasto</option>
      </select>
    </label>
    <label>Audio di sottofondo:<br>
      <select name="audio">
        <option value="none">Nessuno</option>
        <option value="emozionale">Emozionale</option>
      </select>
    </label>
    <label><input type="checkbox" name="privacy" checked> Applica emoji sui volti</label>
    <button type="submit">Avvia Montaggio</button>
  </form>
  <p style="text-align:center;margin-top:1rem;">
    <a href="diagnostica.php" style="color:#666;font-size:.9rem;">Diagnostica sistema</a>
  </p>
</body>
</html>
