<?php
require_once 'config/db.php';

// Get a valid note file path
$stmt = $pdo->query("SELECT file_path FROM notes WHERE note_type = 'pdf' LIMIT 1");
$note = $stmt->fetch();

if ($note) {
    echo "Found PDF in DB: " . $note['file_path'] . "\n";
    
    // Simulate serve_pdf.php logic
    $base_dir = __DIR__;
    $file_path = $base_dir . '/' . $note['file_path'];
    echo "Full Path: " . $file_path . "\n";
    
    if (file_exists($file_path)) {
        echo "File EXISTS.\n";
        echo "Size: " . filesize($file_path) . " bytes\n";
    } else {
        echo "File NOT FOUND.\n";
    }
} else {
    echo "No PDF notes found in DB.\n";
}
?>
