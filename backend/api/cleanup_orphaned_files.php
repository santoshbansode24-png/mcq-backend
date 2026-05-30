<?php
/**
 * Veeru Maintenance: Cleanup Orphaned Files
 * Scans uploads folder and deletes files not present in the database.
 */
require_once '../config/db.php';

// Only allow from CLI or Admin session
if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("Unauthorized access.");
}

$uploadDirs = [
    '../../uploads/class_materials/',
    '../../uploads/notes/',
    '../../uploads/pdf_study/'
];

$deletedCount = 0;
$scannedCount = 0;

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) continue;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $scannedCount++;
        $filePath = $dir . $file;
        $relativeDBPath = str_replace('../../', '', $filePath);

        // Check if file is in database
        $stmt = $pdo->prepare("
            SELECT update_id FROM class_updates WHERE payload LIKE ?
            UNION
            SELECT note_id FROM notes WHERE file_path = ?
            UNION
            SELECT job_id FROM pdf_study_jobs WHERE file_path LIKE ?
        ");
        $stmt->execute(['%'.$file.'%', $relativeDBPath, '%'.$file]);
        
        if (!$stmt->fetch()) {
            // File not in DB - it's an orphan!
            if (unlink($filePath)) {
                $deletedCount++;
                echo "🗑️ Deleted orphaned file: $file\n";
            }
        }
    }
}

echo "\n--- Maintenance Complete ---\n";
echo "Scanned: $scannedCount files\n";
echo "Deleted: $deletedCount orphaned files\n";
?>
