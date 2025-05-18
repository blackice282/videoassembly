<?php
// index.php - Frontend form for video assembly
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Montaggio Video Automatico</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Montaggio Video Verticale 9:16</h1>
        </header>
        <main>
            <form id="montaggioForm" action="upload.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="video">Seleziona video (mp4):</label>
                    <input type="file" id="video" name="videos[]" accept="video/mp4" multiple required>
                </div>
                <div class="form-group">
                    <label for="duration">Durata desiderata (minuti):</label>
                    <input type="number" id="duration" name="duration" min="1" max="60" value="1" required>
                </div>
                <div class="form-group">
                    <label for="instructions">Istruzioni AI:</label>
                    <textarea id="instructions" name="instructions" rows="4" placeholder="Descrivi l'intervento desiderato"></textarea>
                </div>
                <button type="submit" class="btn">Carica e Monta</button>
            </form>
            <section id="response" class="hidden">
                <h2>Risultato</h2>
                <div id="message"></div>
            </section>
        </main>
    </div>
    <script>
    document.getElementById('montaggioForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        const responseEl = document.getElementById('response');
        const messageEl = document.getElementById('message');
        responseEl.classList.add('hidden');
        messageEl.textContent = 'Elaborazione in corso...';
        responseEl.classList.remove('hidden');
        try {
            const res = await fetch(form.action, { method: 'POST', body: data });
            const result = await res.json();
            if (result.success) {
                messageEl.innerHTML = `<a href="${result.path}" target="_blank">Scarica video montato</a>`;
            } else {
                messageEl.textContent = result.error || 'Errore durante il caricamento.';
            }
        } catch (err) {
            messageEl.textContent = 'Errore di rete o server.';
        }
    });
    </script>
</body>
</html>
