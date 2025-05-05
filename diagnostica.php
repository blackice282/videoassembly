<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$config = include __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Diagnostica</title>
</head>
<body>
<h2>Diagnostica Configurazione</h2>
<ul>
<?php foreach ($config as $key => $value): ?>
    <li><?= htmlspecialchars("$key = $value"); ?></li>
<?php endforeach; ?>
</ul>
<h2>PHP Info</h2>
<?php phpinfo(); ?>
</body>
</html>
