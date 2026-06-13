<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../cors_middleware.php';

try {
    $stmt = $pdo->query("SELECT * FROM teacher_classes WHERE teacher_id = 45");
    $rows = $stmt->fetchAll();
    
    echo "TEACHER CLASSES:\n";
    print_r($rows);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
