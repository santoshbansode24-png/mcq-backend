<?php
require_once 'backend/config/db.php';

echo "Checking columns for pdf_study_jobs...\n";
$checkCol = $pdo->query("SHOW COLUMNS FROM pdf_study_jobs LIKE 'pdf_base64'");
if (!$checkCol->fetch()) {
    echo "Adding pdf_base64, folder_id, and study_content columns...\n";
    try {
        $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN pdf_base64 LONGTEXT DEFAULT NULL AFTER file_path");
    } catch (Exception $e) { echo "Error adding pdf_base64: " . $e->getMessage() . "\n"; }
    
    try {
        $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN folder_id INT DEFAULT NULL AFTER user_id");
    } catch (Exception $e) { echo "Error adding folder_id: " . $e->getMessage() . "\n"; }
    
    try {
        $pdo->exec("ALTER TABLE pdf_study_jobs ADD COLUMN study_content LONGTEXT DEFAULT NULL AFTER pdf_base64");
    } catch (Exception $e) { echo "Error adding study_content: " . $e->getMessage() . "\n"; }
    
    try {
        $pdo->exec("ALTER TABLE pdf_study_jobs ADD INDEX (folder_id)");
    } catch (Exception $e) { echo "Error adding index: " . $e->getMessage() . "\n"; }
} else {
    echo "pdf_base64 already exists.\n";
}

$stmt = $pdo->query("DESCRIBE pdf_study_jobs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
?>
