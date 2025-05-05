<?php
require_once 'config.php';
require_once 'duration_editor.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gestione degli errori di upload PHP
    if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Nessun file video caricato.';
    }
    elseif (in_array($_FILES['video']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
        $error = 'File troppo grande. Il limite massimo è ' . ini_get('upload_max_filesize') . '.';
    }
    elseif ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Errore durante l’upload (codice: ' . $_FILES['video']['error'] . ').';
    }
    else {
        $duration = intval($_POST['duration']);
        $result = process_upload_and_mount($_FILES['video'], $duration);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            header('Content-Type: video/mp4');
            header('Content-Disposition: attachment; filename="montaggio.mp4"');
            readfile($result['output_path']);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Montaggio Video Automatico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="#">VideoAssembly</a>
    </div>
  </nav>

  <main class="container my-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="card-title mb-4 text-center">Montaggio Video Automatico</h1>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
              <div class="mb-3">
                <label for="video" class="form-label">Seleziona video</label>
                <input class="form-control" type="file" id="video" name="video" accept="video/*" required>
                <div class="invalid-feedback">Devi caricare un file video.</div>
              </div>
              <div class="mb-4">
                <label for="duration" class="form-label">Durata desiderata (minuti)</label>
                <input type="number" class="form-control" id="duration" name="duration" min="1" max="30" value="3" required>
                <div class="invalid-feedback">Inserisci un numero tra 1 e 30.</div>
              </div>
              <button type="submit" class="btn btn-primary w-100">Carica e Monta</button>
            </form>

          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-white text-center py-3 border-top">
    <small>© <?= date('Y') ?> VideoAssembly. Tutti i diritti riservati.</small>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()
  </script>
</body>
</html>
