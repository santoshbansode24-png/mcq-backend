<?php
$file = $_GET['file'] ?? 'get_notes.php';
echo "Source of $file:\n";
$path = __DIR__ . '/' . basename($file);
if (file_exists($path)) {
    echo file_get_contents($path);
} else {
    echo "File not found at $path";
}
?>
