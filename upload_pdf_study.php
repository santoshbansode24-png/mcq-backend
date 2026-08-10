<?php
if (isset($_GET['debug_test'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'file' => __FILE__,
        'dir' => __DIR__,
        'time' => date('Y-m-d H:i:s'),
        'files_keys' => array_keys($_FILES),
        'post_keys' => array_keys($_POST)
    ]);
    exit();
}
/**
 * Root Upload PDF Study Job Forwarder
 */
if (file_exists(__DIR__ . '/backend/api/upload_pdf_study.php')) {
    require __DIR__ . '/backend/api/upload_pdf_study.php';
} elseif (file_exists(__DIR__ . '/api/upload_pdf_study.php')) {
    require __DIR__ . '/api/upload_pdf_study.php';
} else {
    die(json_encode(['status' => 'error', 'message' => 'upload_pdf_study handler file not found']));
}
?>
