<?php
// index.php - Frontend form for video assembly with real-time debug streaming
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
                <pre id="message"></pre>
            </section>
        </main>
    </div>
    <script>
    document.getElementById('montaggioForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const responseEl = document.getElementById('response');
        const messageEl = document.getElementById('message');
        // Show and clear response area
        responseEl.classList.remove('hidden');
        messageEl.textContent = '';

        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
            if (!res.body) {
                messageEl.textContent = 'Nessun corpo di risposta dal server.';
                return;
            }
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            // Stream chunks and append to message
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                messageEl.textContent += decoder.decode(value);
                // Scroll down as new debug appears
                responseEl.scrollTop = responseEl.scrollHeight;
            }
        } catch (err) {
            messageEl.textContent = 'Errore di rete o server: ' + err.message;
        }
    });
    </script>
</body>
</html>
