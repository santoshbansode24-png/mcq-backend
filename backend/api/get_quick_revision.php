<?php
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

if ($chapter_id <= 0) {
    sendResponse('error', 'Invalid chapter ID');
    exit();
}

try {
    // Check if $pdo exists (from db.php)
    if (!isset($pdo)) {
        throw new Exception("Database connection object (\$pdo) not found.");
    }
    
    $sql = "SELECT revision_id, chapter_id, title, key_points, summary, created_at 
            FROM quick_revision 
            WHERE chapter_id = ? 
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chapter_id]);
    $revisions = $stmt->fetchAll();
    
    // Process JSON fields
    foreach ($revisions as &$row) {
        $key_points = json_decode($row['key_points'], true);
        
        // Function to recursively decode HTML entities
        $decodeFunc = function(&$item) {
            if (is_string($item)) {
                $item = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        
        if (is_array($key_points)) {
            array_walk_recursive($key_points, $decodeFunc);
        }
        
        $row['key_points'] = $key_points;
    }
    
    sendResponse('success', 'Quick revision fetched successfully', $revisions);
    
} catch (Exception $e) {
    error_log("Quick Revision Error: " . $e->getMessage());
    sendResponse('error', 'Failed to fetch quick revision: ' . $e->getMessage());
}
?>
