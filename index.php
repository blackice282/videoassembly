<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ffmpeg_script.php';
require_once __DIR__ . '/people_detection.php';
require_once __DIR__ . '/duration_editor.php';
require_once __DIR__ . '/video_effects.php';
require_once __DIR__ . '/audio_manager.php';
require_once __DIR__ . '/face_detection.php';
require_once __DIR__ . '/privacy_manager.php';

// Job & log
$job     = uniqid();
$logFile = getConfig('paths.temp') . "/progress_{$job}.log";
file_put_contents($logFile, "[START {$job}] " . date('c') . "\n");
function logStep($msg){ global $logFile; file_put_contents($logFile,"[".date('H:i:s')."] $msg\n",FILE_APPEND); }

// Helper: estrae clip proporzionali per durata totale
function getHighlights(array $inputs, int $totalSec): array {
  // Raccogli durate
  $durs = array_map(fn($f)=>getVideoDuration($f), $inputs);
  $sum  = array_sum($durs);
  if($sum <= 0) return $inputs;
  $out  = [];
  foreach($inputs as $i=>$file){
    // durata di questo segmento in proporzione
    $segDur = ($durs[$i]/$sum)*$totalSec;
    $segDur = floor($segDur);
    if($segDur < 1) continue;
    // start a metà clip
    $start = max(0, floor($durs[$i]/2 - $segDur/2));
    $tmp   = getConfig('paths.temp')."/seg_{$i}_".uniqid().".mp4";
    // estrai segment
    shell_exec(sprintf(
      '%s -y -ss %d -i %s -t %d -c copy %s',
      FFMPEG_PATH,
      $start,
      escapeshellarg($file),
      $segDur,
      escapeshellarg($tmp)
    ));
    if(file_exists($tmp) && filesize($tmp)>0){
      $out[] = $tmp;
      logStep("Highlight: {$file} [$start–".$segDur."s] -> ".basename($tmp));
    }
  }
  return count($out)?$out:$inputs;
}

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['videos'])){
  // 1) prepara cartelle
  foreach([getConfig('paths.uploads'),getConfig('paths.temp')] as $d){
    if(!is_dir($d)) mkdir($d,0777,true);
  }
  set_time_limit(0);

  // 2) salva upload
  $clips=[];
  foreach($_FILES['videos']['tmp_name'] as $i=>$tmp){
    if($_FILES['videos']['error'][$i]===UPLOAD_ERR_OK){
      $name='vid_'.uniqid().'_'.basename($_FILES['videos']['name'][$i]);
      $dst = getConfig('paths.uploads').'/'.$name;
      move_uploaded_file($tmp,$dst);
      trackFile($dst,$name,'upload');
      logStep("Uploaded $name");
      $clips[]=$dst;
    }
  }

  // 3) opzioni da form
  $opts = [
    'mode'            => $_POST['mode']            ?? 'simple',
    'duration'        => intval($_POST['duration']) * 60,
    'duration_method' => $_POST['duration_method'] ?? 'trim',
    'effect'          => $_POST['effect']          ?? 'none',
    'audio'           => $_POST['audio']           ?? 'none',
    'privacy'         => isset($_POST['privacy']),
  ];

  // 4) estrai highlights se serve
  if($opts['duration']>0){
    logStep("Selecting highlights for {$opts['duration']}s total");
    $clips = getHighlights($clips, $opts['duration']);
  }

  // 5) concat dei segmenti
  $outputName = 'render_'.date('Ymd_His').'.mp4';
  $outputPath = getConfig('paths.uploads').'/'.$outputName;
  logStep("Concatenating ".count($clips)." clips");
  applyTransitions($clips, $outputPath);
  $out = $outputPath;

  // 6) opzioni extra
  //   - se usi people detection potresti registrare qui
  //   - durata già gestita via highlights

  // 7) effetto video
  if($opts['effect']!=='none'){
    logStep("Applying effect {$opts['effect']}");
    $tmp = getConfig('paths.temp').'/eff_'.uniqid().'.mp4';
    if(applyVideoEffect($out,$tmp,$opts['effect'])){
      $out=$tmp;
    }
  }

  // 8) privacy volti
  if($opts['privacy']){
    logStep("Applying face privacy");
    $tmp=getConfig('paths.temp').'/priv_'.uniqid().'.mp4';
    if(applyFacePrivacy($out,$tmp)){
      $out=$tmp;
    }
  }

  // 9) audio di sottofondo
  if($opts['audio']!=='none'){
    logStep("Applying audio {$opts['audio']}");
    $aud=getRandomAudioFromCategory($opts['audio']);
    if($aud){
      $tmpA=getConfig('paths.temp').'/aud_'.uniqid().'.mp3';
      downloadAudio($aud['url'],$tmpA);
      $tmpV=getConfig('paths.temp').'/audvid_'.uniqid().'.mp4';
      if(applyBackgroundAudio($out,$tmpA,$tmpV,0.3)){
        $out=$tmpV;
      }
    }
  }

  trackFile($out,basename($out),'output');
  logStep("JOB COMPLETE -> {$outputName}");

  // 10) stampa l’interfaccia di monitoraggio + download
  echo <<<HTML
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Elaborazione...</title>
<style>body{font-family:sans-serif;padding:2rem}pre{background:#eee;padding:1rem;height:200px;overflow:auto}</style>
</head><body>
  <h2>🚀 Montaggio in corso…</h2>
  <pre id="log"></pre>
  <script>
    const job="$job";
    setInterval(async()=>{
      let t=await fetch("status.php?job="+job).then(r=>r.text());
      document.getElementById("log").innerText=t;
    },800);
  </script>
  <p>Quando vedi “JOB COMPLETE”, <a href="uploads/$outputName" 
     style="display:inline-block;padding:.8rem 1.2rem;background:#28a745;color:#fff;
            border-radius:4px;text-decoration:none;">
     Scarica il video</a></p>
</body></html>
HTML;
  exit;
}

// --- FORM INIZIALE ---
?>
<!DOCTYPE html>
<html lang="it"><head><meta charset="UTF-8"><title>VideoAssembly</title>
<style>
  body{background:#f4f4f4;margin:0;font-family:sans-serif;padding:2rem;}
  .form-container{max-width:600px;margin:auto;background:#fff;padding:2rem;border-radius:8px;
                  box-shadow:0 2px 10px rgba(0,0,0,.1);}
  .form-group{margin-bottom:1.2rem;}
  label{display:block;font-weight:bold;margin-bottom:.4rem;}
  input[type=file], select, input[type=number]{
    width:100%;padding:.6rem;border:1px solid #ccc;border-radius:4px;
  }
  input[type=checkbox]{transform:scale(1.2);margin-right:.4rem;}
  button{
    width:100%;padding:.8rem;background:#007bff;color:#fff;
    border:none;border-radius:4px;font-size:1.1rem;cursor:pointer;
  }
  button:hover{background:#0056b3;}
</style>
</head><body>
  <div class="form-container">
    <h1 style="text-align:center;">VideoAssembly</h1>
    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label>Carica video:</label>
        <input type="file" name="videos[]" multiple required>
      </div>
      <div class="form-group">
        <label>Modalità:</label>
        <select name="mode">
          <option value="simple">Semplice</option>
          <option value="detect_people">Rileva Persone</option>
        </select>
      </div>
      <div class="form-group">
        <label>Durata totale (minuti):</label>
        <select name="duration">
          <option value="60">1</option>
          <option value="180">3</option>
          <option value="300">5</option>
          <option value="600">10</option>
        </select>
      </div>
      <div class="form-group">
        <label>Effetto video:</label>
        <select name="effect">
          <option value="none">Nessuno</option>
          <option value="bw">Bianco e Nero</option>
          <option value="vintage">Vintage</option>
          <option value="contrast">Contrasto</option>
        </select>
      </div>
      <div class="form-group">
        <label>Audio di sottofondo:</label>
        <select name="audio">
          <option value="none">Nessuno</option>
          <option value="emozionale">Emozionale</option>
        </select>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="privacy" checked> Applica emoji sui volti</label>
      </div>
      <button type="submit">Avvia Montaggio</button>
    </form>
  </div>
</body></html>
