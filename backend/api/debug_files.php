<?php
header('Content-Type: text/plain');

$base = dirname(__DIR__); // /app/backend
$upload_dir = $base . '/uploads';
$notes_dir = $upload_dir . '/notes';

echo "Base Dir: $base\n";
echo "Upload Dir: $upload_dir\n";
echo "Notes Dir: $notes_dir\n\n";

if (is_dir($upload_dir)) {
    echo "Uploads Directory Exists.\n";
    echo "Contents of uploads:\n";
    print_r(scandir($upload_dir));
} else {
    echo "Uploads Directory MISSING.\n";
}

echo "\n";

if (is_dir($notes_dir)) {
    echo "Notes Directory Exists.\n";
    echo "Contents of notes:\n";
    print_r(scandir($notes_dir));
} else {
    echo "Notes Directory MISSING.\n";
}

echo "\nTest Realpath Resolution:\n";
// Test logic from serve_pdf.php
$test_file = (isset($_GET['file'])) ? $_GET['file'] : 'uploads/notes/test.pdf';
$full_path = $base . '/' . $test_file;
echo "Input File: $test_file\n";
echo "Constructed Path: $full_path\n";
echo "Realpath: " . realpath($full_path) . "\n";
echo "Exists? " . (file_exists($full_path) ? 'YES' : 'NO') . "\n";
?>
