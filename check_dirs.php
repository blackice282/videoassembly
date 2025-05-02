<?php
header('Content-Type: text/plain');

$base = __DIR__;

echo "uploads exists:    " . (is_dir("$base/uploads")       ? "yes" : "NO") . "\n";
echo "uploads writable: " . (is_writable("$base/uploads")  ? "yes" : "NO") . "\n";
echo "temp exists:      " . (is_dir("$base/temp")          ? "yes" : "NO") . "\n";
echo "temp writable:    " . (is_writable("$base/temp")     ? "yes" : "NO") . "\n";
?>
