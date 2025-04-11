<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    // Verifica se il file è stato caricato senza errori
    $total_files = count($_FILES['files']['name']);
    for ($i = 0; $i < $total_files; $i++) {
        // Stampa i dati dei file caricati per debug
        echo '<pre>';
        var_dump($_FILES); // Visualizza tutte le informazioni su $_FILES
        echo '</pre>';

        // Gestione degli errori di upload
        switch ($_FILES['files']['error'][$i]) {
            case UPLOAD_ERR_OK:
                echo "File caricato correttamente: " . $_FILES['files']['name'][$i] . "<br>";
                break;
            case UPLOAD_ERR_INI_SIZE:
                echo "Il file " . $_FILES['files']['name'][$i] . " eccede la dimensione massima consentita nel php.ini.<br>";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "Il file " . $_FILES['files']['name'][$i] . " eccede la dimensione massima definita nel modulo.<br>";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "Il file " . $_FILES['files']['name'][$i] . " è stato caricato solo parzialmente.<br>";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "Nessun file caricato per " . $_FILES['files']['name'][$i] . ".<br>";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "Mancante una cartella temporanea per il file " . $_FILES['files']['name'][$i] . ".<br>";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "Impossibile scrivere su disco per il file " . $_FILES['files']['name'][$i] . ".<br>";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "Il caricamento del file " . $_FILES['files']['name'][$i] . " è stato interrotto da un'estensione PHP.<br>";
                break;
            default:
                echo "Errore sconosciuto nel caricamento di " . $_FILES['files']['name'][$i] . ".<br>";
        }

        // Solo se il file è stato caricato senza errori, continua con l'elaborazione
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['files']['tmp_name'][$i];
            $name = $_FILES['files']['name'][$i];
            $destination = 'uploads/' . $name;

            // Crea la cartella se non esiste
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }

            // Sposta il file caricato nella cartella "uploads"
            if (move_uploaded_file($tmp_name, $destination)) {
                echo "File caricato con successo: " . $name . "<br>";
            } else {
                echo "Errore nel caricamento del file: " . $name . "<br>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carica Video</title>
</head>
<body>
    <h1>Carica il tuo video</h1>

    <form method="POST" enctype="multipart/form-data">
        <label for="fileUpload">Seleziona video:</label>
        <input type="file" name="files[]" id="fileUpload" multiple>
        <button type="submit">Carica</button>
    </form>

</body>
</html>
