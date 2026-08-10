<?php
/**
 * API Upload PDF Study Job Forwarder
 */
if (file_exists(__DIR__ . '/../backend/api/upload_pdf_study.php')) {
    require __DIR__ . '/../backend/api/upload_pdf_study.php';
} else {
    die(json_encode(['status' => 'error', 'message' => 'upload_pdf_study handler file not found']));
}
?>
