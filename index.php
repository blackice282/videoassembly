<?php
// Scansione della cartella "musica" per recuperare i file audio
$musicaDir = __DIR__ . '/musica';
$files = glob($musicaDir . '/*.mp3');
$emozionali = [];
$divertenti = [];

foreach ($files as $file) {
    $base = basename($file);
    if (stripos($base, 'emozionale') === 0) {
        $emozionali[] = $base;
    } elseif (stripos($base, 'divertente') === 0) {
        $divertenti[] = $base;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Generazione Video</title>
</head>
<body>
<form method="post" action="ffmpeg_script.php" enctype="multipart/form-data">
  <!-- CAMPPI UPLOAD VIDEO, DURATA, TRANSIZIONI, ECC. -->

  <div>
    <label for="background_file">Seleziona audio di sottofondo:</label>
    <select name="background_file" id="background_file">
      <optgroup label="Emozionale">
        <?php foreach ($emozionali as $audio): ?>
          <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
        <?php endforeach; ?>
      </optgroup>
      <optgroup label="Divertente">
        <?php foreach ($divertenti as $audio): ?>
          <option value="<?= htmlspecialchars($audio) ?>"><?= htmlspecialchars($audio) ?></option>
        <?php endforeach; ?>
      </optgroup>
    </select>
  </div>

  <div>
    <label>Anteprima audio:</label>
    <audio controls id="audioPreview">
      <source id="audioSource" src="" type="audio/mpeg">
      Il tuo browser non supporta l'elemento audio.
    </audio>
  </div>

  <!-- Pulsante di invio -->
  <button type="submit">Genera video</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var select = document.getElementById('background_file');
  var audio = document.getElementById('audioPreview');
  var source = document.getElementById('audioSource');

  function updatePreview() {
    var file = select.value;
    source.src = 'musica/' + file;
    audio.load();
  }

  select.addEventListener('change', updatePreview);
  if (select.value) updatePreview();
});
</script>
</body>
</html>
