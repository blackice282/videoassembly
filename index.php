<?php
// DEBUG: mostra tutti gli errori
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CONFIG e librerie
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
require_once 'privacy_manager.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';

// Assicuriamoci che cartelle esistano
function createDirs() {
    $dirs = [
        getConfig('paths.uploads', 'uploads'),
        getConfig('paths.temp', 'temp'),
        'logs',
    ];
    foreach ($dirs as $d) {
        if (!file_exists($d)) {
            mkdir($d, 0777, true);
        }
    }
}

createDirs();
$logFile = 'logs/app.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " — index.php avviato\n", FILE_APPEND);

// POST?
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['videos']['tmp_name']);

if ($isPost) {
    // Aumentiamo il timeout se serve
    set_time_limit(600);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " — Ricevuto POST, inizio upload file\n", FILE_APPEND);

    // Preparo opzioni
    $options = [
        'mode'            => $_POST['mode'] ?? 'simple',
        'duration'        => (int)($_POST['duration'] ?? 0) * 60,
        'duration_method' => $_POST['duration_method'] ?? 'trim',
        'apply_effect'    => isset($_POST['apply_effect']),
        'video_effect'    => $_POST['video_effect'] ?? 'none',
        'audio'           => $_POST['audio'] ?? 'none',
        'privacy'         => isset($_POST['privacy']),
    ];
    file_put_contents($logFile, date('Y-m-d H:i:s') . " — Opzioni: " . json_encode($options) . "\n", FILE_APPEND);

    // Muovo i file in uploads/
    $tempVideos = [];
    foreach ($_FILES['videos']['tmp_name'] as $i => $tmpName) {
        $originalName = basename($_FILES['videos']['name'][$i]);
        if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
            $dest = getConfig('paths.uploads', 'uploads') . '/' . uniqid('video_') . '_' . $originalName;
            if (move_uploaded_file($tmpName, $dest)) {
                $tempVideos[] = $dest;
                file_put_contents($logFile, date('Y-m-d H:i:s') . " — Caricato: $dest\n", FILE_APPEND);
            } else {
                $msg = "Errore nel move_uploaded_file per $originalName";
                file_put_contents($logFile, date('Y-m-d H:i:s') . " — $msg\n", FILE_APPEND);
                echo "<pre>$msg</pre>";
                exit;
            }
        } else {
            $msg = "Upload error code {$_FILES['videos']['error'][$i]} per $originalName";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " — $msg\n", FILE_APPEND);
            echo "<pre>$msg</pre>";
            exit;
        }
    }

    // Chiamata al process chain
    file_put_contents($logFile, date('Y-m-d H:i:s') . " — Avvio processVideoChain con " . count($tempVideos) . " file\n", FILE_APPEND);
    try {
        $output = processVideoChain($tempVideos, $options);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " — processVideoChain terminato, output: $output\n", FILE_APPEND);
    } catch (Exception $e) {
        $err = "Exception in processVideoChain: " . $e->getMessage();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " — $err\n", FILE_APPEND);
        echo "<pre>$err</pre>";
        exit;
    }
}
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>VideoAssembly</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header><h1>VideoAssembly</h1></header>
  <main class="container">
    <?php if ($isPost): ?>
      <h2>Video Elaborato</h2>
      <p><a href="<?php echo htmlspecialchars($output); ?>" download>Scarica il video</a></p>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <label>Seleziona video:
          <input type="file" name="videos[]" multiple required>
        </label>
        <label>Durata desiderata (minuti):
          <input type="number" name="duration" min="1" value="3">
        </label>
        <label>Modalità:
          <select name="mode">
            <option value="simple">Semplice</option>
            <option value="detect_people">Rileva Persone</option>
          </select>
        </label>
        <div class="options">
          <label><input type="checkbox" name="apply_effect"> Applica effetto video</label>
          <label>Effetto:
            <select name="video_effect">
              <?php foreach (getVideoEffects() as $key => $eff): ?>
                <option value="<?php echo $key; ?>"><?php echo ucfirst($key); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Audio di sottofondo:
            <select name="audio">
              <option value="none">Nessuno</option>
              <option value="relax">Relax</option>
              <option value="upbeat">Upbeat</option>
            </select>
          </label>
          <label><input type="checkbox" name="privacy"> Privacy volti</label>
        </div>
        <button type="submit">Carica e Monta</button>
      </form>
    <?php endif; ?>
  </main>
  <footer><p>© <?=date('Y')?> VideoAssembly</p></footer>
</body>
</html>
