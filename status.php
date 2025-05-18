<?php
// status.php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = getConfig('paths.uploads');
    $duration = $_POST['duration'];
    $files = $_FILES['videos'];

    $jobId = uniqid();
    $jobFile = "$uploadDir/$jobId.job";

    // Move uploaded files and create job
    foreach ($files['tmp_name'] as $idx => $tmp) {
        if (is_uploaded_file($tmp)) {
            $ext = pathinfo($files['name'][$idx], PATHINFO_EXTENSION);
            $dest = "$uploadDir/$jobId_$idx.$ext";
            move_uploaded_file($tmp, $dest);
            // For simplicity, only first file is processed
            $inputPath = $dest;
            break;
        }
    }
    file_put_contents($jobFile, json_encode(['input' => $inputPath, 'duration' => $duration]));

    echo "Job creato: $jobId. Esegui worker.php per processare.";
    exit;
}

if (isset($_GET['job'])) {
    $jobId = $_GET['job'];
    $doneFile = getConfig('paths.uploads') . "/$jobId.job.done";
    if (file_exists($doneFile)) {
        $data = json_decode(file_get_contents($doneFile), true);
        if (isset($data['output'])) {
            $url = getConfig('system.base_url') . '/' . $data['output'];
            echo "<a href="$url">Scarica video</a>";
        } else {
            echo "Errore: " . $data['error'];
        }
    } else {
        echo "In lavorazione...";
    }
    exit;
}
?>