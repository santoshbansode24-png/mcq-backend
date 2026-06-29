<?php
$downloadsDir = 'C:\Users\ADMIN\Downloads';
if (is_dir($downloadsDir)) {
    echo "Scanning Downloads folder...\n";
    $files = scandir($downloadsDir);
    foreach ($files as $f) {
        if (strpos($f, 'mcq-student-app') !== false) {
            echo "Found file: $f (Size: " . filesize($downloadsDir . DIRECTORY_SEPARATOR . $f) . " bytes)\n";
        }
    }
} else {
    echo "Downloads directory not found at $downloadsDir\n";
}
?>
