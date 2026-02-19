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

$stmt = $pdo->query("SELECT note_id, title, file_path, created_at FROM notes WHERE note_type = 'pdf' ORDER BY created_at DESC LIMIT 1");
$note = $stmt->fetch();

if ($note) {
    echo "Note ID: {$note['note_id']}\n";
    echo "Title: {$note['title']}\n";
    echo "Filesystem Path / URL: {$note['file_path']}\n";
    echo "Created: {$note['created_at']}\n";
    
    // Check if it's an S3 URL
    if (strpos($note['file_path'], 's3.amazonaws.com') !== false || strpos($note['file_path'], 'http') === 0) {
        echo "\n✅ STORAGE: AWS S3 / REMOTE SERVER\n";
        echo "The file is stored on the cloud. The app will download it from this URL.\n";
    } else {
        echo "\n⚠️ STORAGE: LOCAL SERVER (Not AWS)\n";
        echo "The file is stored in your 'uploads/' folder.\n";
        
        $full_path = __DIR__ . '/../' . $note['file_path'];
        if (file_exists($full_path)) {
            echo "File verified on disk (Size: " . filesize($full_path) . " bytes)\n";
        } else {
            echo "❌ File missing from local disk!\n";
        }
    }
} else {
    echo "No uploaded PDFs found in database.\n";
}
?>
