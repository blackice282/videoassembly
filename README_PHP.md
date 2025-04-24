# VideoAssembly – PHP App (Render)

Questo progetto PHP esegue il montaggio video automatizzato, includendo:
- Applicazione di effetti visivi (bianco/nero, vintage, contrasto)
- Aggiunta di audio di sottofondo
- Rilevamento persone e gestione durate
- Chiamata a un microservizio Python per offuscamento volti tramite smile (escludendo operatori con pettorina gialla)

## ⚙ Requisiti
- PHP 8.2+
- FFmpeg installato nel container (incluso via Docker)
- Porta esposta: `10000`

## 🐳 Deploy su Render
Assicurati che la porta esposta sia `10000`. Il comando di avvio è:
```bash
php -S 0.0.0.0:10000
```

## 🧠 Integrazione Microservizio
Modifica l'URL nel file `face_detection.php` per puntare al tuo microservizio Flask:
```php
CURLOPT_URL => 'https://TUO_MICROSERVIZIO.onrender.com/process'
```
