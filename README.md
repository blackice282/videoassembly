# VideoAssembly - Sistema di Montaggio Video Automatico

Questo sistema permette di creare automaticamente video montati a partire da clip esistenti, con funzionalità di base:

- Upload multiplo di video
- Selezione della durata desiderata
- Download del video processato (copia del file caricato)

## Requisiti

- PHP 7.2+
- FFmpeg installato
- Docker (opzionale, per facilità di deploy)

## Installazione

1. Clona o scarica il repository.
2. Esegui `docker build -t videoassembly .` (se usi Docker)
3. Avvia il servizio: `php -S 0.0.0.0:10000 index.php`
4. Apri il browser su `http://localhost:10000`

## Utilizzo

1. Carica uno o più video (formato mp4, mov, avi, mkv).
2. Imposta la durata desiderata.
3. Clicca "Carica e Monta".
4. Scarica il video processato.

## Debug

- Accedi a `diagnostica.php` per vedere la configurazione e il phpinfo().
- I file caricati sono in `uploads/`, quelli processati in `processed/`.
