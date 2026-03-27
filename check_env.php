<?php
$dir = 'uploads/pdf_study';
if (!is_dir($dir)) {
    if (mkdir($dir, 0777, true)) {
        echo "✅ Directory created successfully.\n";
    } else {
        echo "❌ FAILED to create directory.\n";
    }
} else {
    echo "✅ Directory already exists.\n";
}

if (is_writable($dir)) {
    echo "✅ Directory is WRITABLE.\n";
} else {
    echo "❌ Directory is NOT WRITABLE.\n";
}

// Check vendor
if (file_exists('vendor/autoload.php')) {
    echo "✅ Vendor autoload found.\n";
} else {
    echo "❌ Vendor autoload MISSING.\n";
}
?>
