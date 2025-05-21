
<?php
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ob_implicit_flush(true);
while (ob_get_level() > 0) { ob_end_flush(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['videos'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $uploaded = [];
    foreach ($_FILES['videos']['tmp_name'] as $i => $tmpName) {
        $name = basename($_FILES['videos']['name'][$i]);
        $dest = $uploadDir . $name;
        if (move_uploaded_file($tmpName, $dest)) {
            $uploaded[] = $dest;
        }
    }
    if ($uploaded) {
        $listFile = $uploadDir . 'list.txt';
        $handle = fopen($listFile, 'w');
        foreach ($uploaded as $file) {
            fwrite($handle, "file '" . str_replace("'", "'\\''", $file) . "'\n");
        }
        fclose($handle);

        $outputFile = $uploadDir . 'output.mp4';
        $cmdConcat = "ffmpeg -f concat -safe 0 -i \"$listFile\" -c copy \"$outputFile\"";
        exec($cmdConcat, $outConcat, $retConcat);
        echo "<pre>CMD: $cmdConcat\n";
        print_r($outConcat);
        echo "\nRET: $retConcat</pre>";
        @ob_flush(); @flush();

        if ($retConcat !== 0 || !file_exists($outputFile)) {
            echo "<p>Errore nella concatenazione dei video.</p>";
            @ob_flush(); @flush();
            exit;
        }

        $maxDuration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
        $tipoMontaggio = isset($_POST['montaggio']) ? $_POST['montaggio'] : 'semplice';

        switch ($tipoMontaggio) {
            case 'persone':
                $pythonScript = __DIR__ . '/detect_people.py';
                $outputPersone = $uploadDir . 'output_people.mp4';
                $cmd = "python \"$pythonScript\" \"$outputFile\" \"$outputPersone\"";
                exec($cmd, $out, $ret);
                echo "<pre>CMD: $cmd\n";
                print_r($out);
                echo "\nRET: $ret</pre>";
                @ob_flush(); @flush();
                if ($ret === 0 && file_exists($outputPersone)) {
                    if ($maxDuration > 0) {
                        $tempFile = $uploadDir . 'output_people_cut.mp4';
                        $cmdCut = "ffmpeg -y -i \"$outputPersone\" -t $maxDuration -c copy \"$tempFile\"";
                        exec($cmdCut, $outCut, $retCut);
                        echo "<pre>CMD: $cmdCut\n";
                        print_r($outCut);
                        echo "\nRET: $retCut</pre>";
                        @ob_flush(); @flush();
                        if ($retCut === 0 && file_exists($tempFile)) {
                            unlink($outputPersone);
                            rename($tempFile, $outputPersone);
                        }
                    }
                    if (file_exists($outputPersone)) {
                        echo "<p>Video con persone montato! <a href='uploads/output_people.mp4' download>Scarica il video</a></p>";
                    } else {
                        echo "<p>Errore nel taglio finale del video persone.</p>";
                    }
                } else {
                    echo "<p>Errore nel rilevamento persone.</p>";
                }
                break;
            case 'interazioni':
                $pythonScript = __DIR__ . '/detect_interactions.py';
                $outputInterazioni = $uploadDir . 'output_interazioni.mp4';
                $cmd = "C:\\Users\\282\\AppData\\Local\\Programs\\Python\\Python313\\python.exe \"$pythonScript\" \"$outputFile\" \"$outputInterazioni\"";
                exec($cmd, $out, $ret);
                echo "<pre>CMD: $cmd\n";
                print_r($out);
                echo "\nRET: $ret</pre>";
                @ob_flush(); @flush();
                if ($ret === 0 && file_exists($outputInterazioni)) {
                    if ($maxDuration > 0) {
                        $tempFile = $uploadDir . 'output_interazioni_cut.mp4';
                        $cmdCut = "ffmpeg -y -i \"$outputInterazioni\" -t $maxDuration -c copy \"$tempFile\"";
                        exec($cmdCut, $outCut, $retCut);
                        echo "<pre>CMD: $cmdCut\n";
                        print_r($outCut);
                        echo "\nRET: $retCut</pre>";
                        @ob_flush(); @flush();
                        if ($retCut === 0 && file_exists($tempFile)) {
                            unlink($outputInterazioni);
                            rename($tempFile, $outputInterazioni);
                        }
                    }
                    if (file_exists($outputInterazioni)) {
                        echo "<p>Video con interazioni montato! <a href='uploads/output_interazioni.mp4' download>Scarica il video</a></p>";
                    } else {
                        echo "<p>Errore nel taglio finale del video interazioni.</p>";
                    }
                } else {
                    echo "<p>Errore nel rilevamento interazioni.</p>";
                }
                break;
            case 'migliori':
                $pythonScript = __DIR__ . '/detect_best_scenes.py';
                $outputMigliori = $uploadDir . 'output_migliori.mp4';
                $cmd = "python \"$pythonScript\" \"$outputFile\" \"$outputMigliori\"";
                exec($cmd, $out, $ret);
                echo "<pre>CMD: $cmd\n";
                print_r($out);
                echo "\nRET: $ret</pre>";
                @ob_flush(); @flush();
                if ($ret === 0 && file_exists($outputMigliori)) {
                    if ($maxDuration > 0) {
                        $tempFile = $uploadDir . 'output_migliori_cut.mp4';
                        $cmdCut = "ffmpeg -y -i \"$outputMigliori\" -t $maxDuration -c copy \"$tempFile\"";
                        exec($cmdCut, $outCut, $retCut);
                        echo "<pre>CMD: $cmdCut\n";
                        print_r($outCut);
                        echo "\nRET: $retCut</pre>";
                        @ob_flush(); @flush();
                        if ($retCut === 0 && file_exists($tempFile)) {
                            unlink($outputMigliori);
                            rename($tempFile, $outputMigliori);
                        }
                    }
                    if (file_exists($outputMigliori)) {
                        echo "<p>Video con migliori scene montato! <a href='uploads/output_migliori.mp4' download>Scarica il video</a></p>";
                    } else {
                        echo "<p>Errore nel taglio finale del video migliori scene.</p>";
                    }
                } else {
                    echo "<p>Errore nella selezione migliori scene.</p>";
                }
                break;
            case 'semplice':
            default:
                if ($maxDuration > 0) {
                    $tempFile = $uploadDir . 'output_cut.mp4';
                    $cmdCut = "ffmpeg -y -i \"$outputFile\" -t $maxDuration -c copy \"$tempFile\"";
                    exec($cmdCut, $outCut, $retCut);
                    echo "<pre>CMD: $cmdCut\n";
                    print_r($outCut);
                    echo "\nRET: $retCut</pre>";
                    @ob_flush(); @flush();
                    if ($retCut === 0 && file_exists($tempFile)) {
                        unlink($outputFile);
                        rename($tempFile, $outputFile);
                    }
                }
                if (file_exists($outputFile)) {
                    echo "<p>Video montato con successo! <a href='uploads/output.mp4' download>Scarica il video finale</a></p>";
                } else {
                    echo "<p>Errore nel montaggio video.</p>";
                }
                break;
        }
    } else {
        echo "<p>Nessun file caricato.</p>";
        @ob_flush(); @flush();
    }
}
?>

<h1>VideoAssembly - Upload Video</h1>
<form method="post" enctype="multipart/form-data">
    <label>Seleziona uno o più video MP4 da caricare:</label><br>
    <input type="file" name="videos[]" accept="video/mp4" multiple required><br><br>
    <label>Durata massima video finale (secondi, opzionale):</label><br>
    <input type="number" name="duration" min="1" placeholder="es: 60"><br><br>
    <label>Tipo di montaggio:</label><br>
    <select name="montaggio" required>
        <option value="semplice">Montaggio semplice (concatena i video)</option>
        <option value="persone">Rilevamento persone (solo parti con persone)</option>
        <option value="interazioni">Interazioni tra persone</option>
        <option value="migliori">Selezione migliori scene</option>
    </select><br><br>
    <button type="submit">Carica e Monta Video</button>
</form>