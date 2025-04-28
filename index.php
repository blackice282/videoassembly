<?php
echo "START INDEX.PHP OK<br>";
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Loading config.php...<br>";
require_once 'config.php';

echo "Loading ffmpeg_script.php...<br>";
require_once 'ffmpeg_script.php';

echo "Loading people_detection.php...<br>";
require_once 'people_detection.php';

echo "Loading transitions.php...<br>";
require_once 'transitions.php';

echo "Loading duration_editor.php...<br>";
require_once 'duration_editor.php';

echo "Loading video_effects.php...<br>";
require_once 'video_effects.php';

echo "Loading audio_manager.php...<br>";
require_once 'audio_manager.php';

echo "Loading face_detection.php...<br>";
require_once 'face_detection.php';

echo "Tutti i file caricati correttamente!<br>";
?>
