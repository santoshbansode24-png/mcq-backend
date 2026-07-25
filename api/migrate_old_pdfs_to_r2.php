<?php
/**
 * One-Click Migration Script: Migrate Old PDFs from Railway Storage to Cloudflare R2
 * Endpoint: GET/POST /api/migrate_old_pdfs_to_r2.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config/aws-config.php';

header('Content-Type: application/json; charset=UTF-8');

$results = [
    'status' => 'success',
    'total_found' => 0,
    'migrated' => 0,
    'failed' => 0,
    'details' => []
];

try {
    // 1. Fetch all notes with local file paths (not starting with http)
    $stmt = $pdo->prepare("SELECT note_id, title, file_path FROM notes WHERE file_path NOT LIKE 'http%' AND file_path IS NOT NULL AND file_path != ''");
    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results['total_found'] = count($notes);

    foreach ($notes as $note) {
        $note_id = $note['note_id'];
        $title = $note['title'];
        $file_path = ltrim($note['file_path'], '/');

        // Locate file on local Railway server
        $possiblePaths = [
            __DIR__ . '/../../' . $file_path,
            __DIR__ . '/../' . $file_path,
            __DIR__ . '/../../../' . $file_path,
            '/app/' . $file_path,
            '/app/backend/' . $file_path
        ];

        $foundLocalPath = false;
        foreach ($possiblePaths as $p) {
            if (file_exists($p)) {
                $foundLocalPath = $p;
                break;
            }
        }

        // If file missing on server disk, try fetching via HTTP
        $tempDownloaded = false;
        if (!$foundLocalPath) {
            $fetchUrl = "https://api.veeruapp.in/api/serve_pdf.php?file=" . urlencode($file_path);
            $tmpFile = sys_get_temp_dir() . '/mig_' . basename($file_path);
            
            $ch = curl_init($fetchUrl);
            $fp = fopen($tmpFile, 'w+');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($httpCode === 200 && filesize($tmpFile) > 500) {
                $foundLocalPath = $tmpFile;
                $tempDownloaded = true;
            }
        }

        if (!$foundLocalPath) {
            $results['failed']++;
            $results['details'][] = [
                'note_id' => $note_id,
                'title' => $title,
                'status' => 'failed',
                'error' => 'File not found on server disk or HTTP'
            ];
            continue;
        }

        // Upload to Cloudflare R2
        $s3_key = "notes/" . basename($file_path);
        $r2_url = uploadToS3($foundLocalPath, $s3_key);

        if ($tempDownloaded && file_exists($foundLocalPath)) {
            @unlink($foundLocalPath);
        }

        if ($r2_url) {
            // Update database to point to Cloudflare R2 URL
            $updateStmt = $pdo->prepare("UPDATE notes SET file_path = ? WHERE note_id = ?");
            $updateStmt->execute([$r2_url, $note_id]);

            $results['migrated']++;
            $results['details'][] = [
                'note_id' => $note_id,
                'title' => $title,
                'status' => 'success',
                'old_path' => $file_path,
                'new_r2_url' => $r2_url
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'note_id' => $note_id,
                'title' => $title,
                'status' => 'failed',
                'error' => 'R2 Upload failed. Check R2 credentials.'
            ];
        }
    }

    echo json_encode($results, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
