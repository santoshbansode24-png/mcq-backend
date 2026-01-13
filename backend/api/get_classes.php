<?php
/**
 * Get Classes API
 * Veeru
 * 
 * Endpoint: GET /api/get_classes.php
 * Purpose: Get all available classes
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

// Only allow GET requests
file_put_contents('../debug_classes.log', date('Y-m-d H:i:s') . " - Request received from " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

try {
    // Check for board filter
    $boardInfo = isset($_GET['board']) ? $_GET['board'] : null;
    $sql = "
        SELECT 
            class_id,
            class_name,
            board_type
        FROM classes
    ";
    
    $params = [];
    if ($boardInfo) {
        $sql .= " WHERE board_type = ? ";
        $params[] = $boardInfo;
    }
    
    $sql .= " ORDER BY class_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();
    
    // Check if classes exist
    if (empty($classes)) {
        sendResponse('success', 'No classes found', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Classes retrieved successfully', $classes, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
