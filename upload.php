<?php
// upload.php
header('Content-Type: text/plain');
function logDebug($msg) {
    echo $msg . "\n";
    ob_flush();
    flush();
}

logDebug("Ricevuti dati: " . print_r($_POST, true));
logDebug("Inizio upload di " . count($_FILES['videos']['name']) . " file...");
// Qui sposti i file, avvii FFmpeg, ecc., usi logDebug() per ogni passo
// Al termine:
logDebug("Montaggio completato.");
logDebug("URL finale: " . $url);
?>
