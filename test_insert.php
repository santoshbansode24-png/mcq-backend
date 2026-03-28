<?php
require 'backend/config/db.php';
try {
   $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, ?, 'pending', 10, 0)");
   $stmt->execute([1, null, "test.pdf", "test.pdf"]);
   echo "Success!";
} catch (Exception $e) {
   echo "Error: " . $e->getMessage();
}
?>
