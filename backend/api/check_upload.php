<?php
header('Content-Type: text/plain');

echo "=== Quick Upload Test ===\n\n";

$upload_dir = __DIR__ . '/../uploads/notes';
echo "Upload Directory: $upload_dir\n";
echo "Exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "\n";
echo "Writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "\n\n";

if (is_dir($upload_dir)) {
    $files = array_diff(scandir($upload_dir), ['.', '..']);
    echo "Files currently in directory: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "  - $file\n";
    }
}

echo "\n=== Latest PDF Upload in Database ===\n";
require_once '../config/db.php';

$stmt = $pdo->query("SELECT note_id, title, file_path, created_at FROM notes WHERE note_type = 'pdf' AND file_path NOT LIKE 'http%' ORDER BY created_at DESC LIMIT 1");
$note = $stmt->fetch();

if ($note) {
    echo "Note ID: {$note['note_id']}\n";
    echo "Title: {$note['title']}\n";
    echo "Path in DB: {$note['file_path']}\n";
    echo "Created: {$note['created_at']}\n";
    
    $full_path = __DIR__ . '/../' . $note['file_path'];
    echo "Full Path: $full_path\n";
    echo "File Exists: " . (file_exists($full_path) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($full_path)) {
        echo "File Size: " . filesize($full_path) . " bytes\n";
        echo "\n✅ THIS FILE SHOULD WORK!\n";
    } else {
        echo "\n❌ FILE MISSING - Upload a new PDF via Admin Panel\n";
    }
} else {
    echo "No uploaded PDFs found in database.\n";
}
?>
