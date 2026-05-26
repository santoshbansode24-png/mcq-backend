<?php
require 'backend/config/db.php';
$stmt = $pdo->query("SELECT job_id, user_id, status, file_path, CHAR_LENGTH(pdf_base64) as b64_len, CHAR_LENGTH(extracted_text) as text_len FROM pdf_study_jobs ORDER BY job_id DESC LIMIT 5");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
