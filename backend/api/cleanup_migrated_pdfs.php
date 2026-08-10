<?php
/**
 * Cleanup Local PDF Files Migrated to Cloudflare R2
 * Deletes physical local copies on Railway server disk ONLY IF they exist on R2.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

$response = [
    'status' => 'success',
    'deleted_files' => 0,
    'freed_bytes' => 0,
    'freed_mb' => 0,
    'details' => []
];

try {
    // Select all notes pointing to Cloudflare R2
    $stmt = $pdo->prepare("SELECT note_id, title, file_path FROM notes WHERE file_path LIKE '%r2.dev%' OR file_path LIKE 'http%'");
    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseDir = dirname(__DIR__); // /app/backend
    $uploadDir = $baseDir . '/uploads/notes/';

    foreach ($notes as $note) {
        $filename = basename($note['file_path']);
        if (empty($filename)) continue;

        $localPath = $uploadDir . $filename;
        if (file_exists($localPath)) {
            $bytes = filesize($localPath);
            if (@unlink($localPath)) {
                $response['deleted_files']++;
                $response['freed_bytes'] += $bytes;
                $response['details'][] = [
                    'note_id' => $note['note_id'],
                    'title' => $note['title'],
                    'filename' => $filename,
                    'freed_bytes' => $bytes
                ];
            }
        }
    }

    $response['freed_mb'] = round($response['freed_bytes'] / (1024 * 1024), 2);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
