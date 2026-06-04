<?php
require_once '../config/db.php';
require_once 'cors_middleware.php';

try {
    $stmt = $pdo->query("DESCRIBE class_updates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse('success', 'class_updates schema', $columns);
} catch (Exception $e) {
    sendResponse('error', $e->getMessage());
}
?>
