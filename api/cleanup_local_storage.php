<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$deleted = [];
$dirs = [
    __DIR__ . '/../uploads/notes/',
    __DIR__ . '/../uploads/materials/'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*.pdf');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                    $deleted[] = basename($file);
                }
            }
        }
    }
}

echo json_encode([
    'status' => 'success',
    'deleted_count' => count($deleted),
    'deleted_files' => $deleted,
    'message' => 'Local PDF files successfully purged from Railway disk space!'
]);
?>
