<?php
echo "START INDEX.PHP OK<br>";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';

// Solo diagnostica per ora!
?>
