// Aggiungi questo require in cima a index.php, dopo le altre inclusioni
require_once 'face_detection.php';

// Aggiungi questa parte all'interno del div "options" nel form:

                <div class="option-group">
                    <h3>Privacy dei volti:</h3>
                    <label>
                        <input type="checkbox" name="apply_face_privacy" value="1"> 
                        Applica emoji sorridenti sui volti
                    </label>
                    <label>
                        <input type="checkbox" name="exclude_yellow_vests" value="1" checked> 
                        Escludi persone con pettorine gialle
                    </label>
                </div>

// Trova la parte dove gestisci l'audio di sottofondo in index.php
// (intorno alla riga 252) e sostituiscila con questo:

                // Applica audio di sottofondo se richiesto
                if (!empty($audioCategory)) {
                    echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . " (non interrompe il parlato)...<br>";
                    $audio = getRandomAudioFromCategory($audioCategory);
                    
                    if ($audio) {
                        // Scarica l'audio se necessario
                        $audioDir = getConfig('paths.temp', 'temp') . '/audio';
                        if (!file_exists($audioDir)) {
                            mkdir($audioDir, 0777, true);
                        }
                        
                        $audioFile = $audioDir . '/' . basename($audio['url']);
                        if (!file_exists($audioFile)) {
                            $downloadSuccess = downloadAudio($audio['url'], $audioFile);
                            if (!$downloadSuccess) {
                                echo "⚠️ Impossibile scaricare l'audio.<br>";
                                // Usa un audio locale se disponibile
                                $localAudio = 'assets/audio/default_background.mp3';
                                if (file_exists($localAudio)) {
                                    $audioFile = $localAudio;
                                    echo "✅ Utilizzo audio di backup locale<br>";
                                } else {
                                    $audioFile = null;
                                }
                            }
                        }
                        
                        // Applica l'audio al video se disponibile
                        if ($audioFile && file_exists($audioFile)) {
                            $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
                            if (applyBackgroundAudio($outputFinal, $audioFile, $outputWithAudio, $audioVolume)) {
                                // Verifica che il file esista e abbia dimensioni
                                if (file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
                                    // Se l'audio è stato applicato con successo, usa il nuovo file
                                    unlink($outputFinal); // Rimuovi il file senza audio
                                    $outputFinal = $outputWithAudio;
                                    echo "✅ Audio aggiunto: " . $audio['name'] . " (ottimizzato per non coprire il parlato)<br>";
                                } else {
                                    echo "⚠️ File con audio danneggiato, si utilizza l'originale<br>";
                                }
                            } else {
                                echo "⚠️ Non è stato possibile aggiungere l'audio.<br>";
                            }
                        }
                    }
                }
                
                // Aggiungi privacy dei volti se richiesta
                $applyFacePrivacy = isset($_POST['apply_face_privacy']);
                $excludeYellowVests = isset($_POST['exclude_yellow_vests']);
                
                if ($applyFacePrivacy) {
                    // Verifica se OpenCV è disponibile
                    $opencvAvailable = isOpenCVAvailable();
                    
                    if ($opencvAvailable) {
                        echo "😊 Applicazione emoji sorridenti sui volti per privacy...<br>";
                        $outputWithFacePrivacy = getConfig('paths.uploads', 'uploads') . '/video_privacy_' . $timestamp . '.mp4';
                        
                        if (applyFacePrivacy($outputFinal, $outputWithFacePrivacy, $excludeYellowVests)) {
                            if (file_exists($outputWithFacePrivacy) && filesize($outputWithFacePrivacy) > 0) {
                                unlink($outputFinal); // Rimuovi il file senza privacy
                                $outputFinal = $outputWithFacePrivacy;
                                echo "✅ Privacy dei volti applicata";
                                if ($excludeYellowVests) {
                                    echo " (escluse persone con pettorine gialle)";
                                }
                                echo "<br>";
                            } else {
                                echo "⚠️ File con privacy volti danneggiato, si utilizza l'originale<br>";
                            }
                        } else {
                            echo "⚠️ Non è stato possibile applicare la privacy dei volti.<br>";
                        }
                    } else {
                        echo "⚠️ OpenCV non disponibile per la privacy dei volti.<br>";
                        echo "Per utilizzare questa funzione, installa OpenCV per Python:<br>";
                        echo "<code>pip install opencv-python</code><br>";
                    }
                }
