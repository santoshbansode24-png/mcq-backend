<?php
/**
 * Get Quick Revision API (Multi-Language Supported)
 * Veeru
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Invalid request method');
    exit();
}

$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$lang = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? 'en'));

if ($chapter_id <= 0) {
    sendResponse('error', 'Invalid chapter ID');
    exit();
}

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection object (\$pdo) not found.");
    }
    
    $sql = "SELECT * FROM quick_revision WHERE chapter_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chapter_id]);
    $rows = $stmt->fetchAll();
    
    $revisions = [];
    foreach ($rows as $row) {
        $title = $row['title'];
        $summary = $row['summary'];
        $kpRaw = $row['key_points'];

        if ($lang === 'hi') {
            if (!empty($row['title_hi'])) $title = $row['title_hi'];
            if (!empty($row['summary_hi'])) $summary = $row['summary_hi'];
            if (!empty($row['key_points_hi'])) $kpRaw = $row['key_points_hi'];
        } elseif ($lang === 'mr') {
            if (!empty($row['title_mr'])) $title = $row['title_mr'];
            if (!empty($row['summary_mr'])) $summary = $row['summary_mr'];
            if (!empty($row['key_points_mr'])) $kpRaw = $row['key_points_mr'];
        }

        $key_points = is_array($kpRaw) ? $kpRaw : json_decode($kpRaw, true);

        $decodeFunc = function(&$item) {
            if (is_string($item)) {
                $item = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        
        if (is_array($key_points)) {
            array_walk_recursive($key_points, $decodeFunc);
        }

        $revisions[] = [
            'revision_id' => $row['revision_id'],
            'chapter_id' => $row['chapter_id'],
            'title' => $title,
            'key_points' => $key_points ?: [],
            'summary' => $summary,
            'created_at' => $row['created_at']
        ];
    }
    
    sendResponse('success', 'Quick revision fetched successfully', $revisions);
    
} catch (Exception $e) {
    error_log("Quick Revision Error: " . $e->getMessage());
    sendResponse('error', 'Failed to fetch quick revision: ' . $e->getMessage());
}
