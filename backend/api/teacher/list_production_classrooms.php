<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../cors_middleware.php';

try {
    $stmt = $pdo->query("SELECT class_id, teacher_id, class_code, class_name FROM classrooms ORDER BY class_id DESC LIMIT 30");
    $classrooms = $stmt->fetchAll();
    
    echo "CLASSROOMS IN PRODUCTION:\n";
    echo str_pad("Class ID", 10) . " | " . str_pad("Teacher ID", 12) . " | " . str_pad("Class Code", 12) . " | Class Name\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($classrooms as $c) {
        echo str_pad($c['class_id'], 10) . " | " . str_pad($c['teacher_id'], 12) . " | " . str_pad($c['class_code'], 12) . " | " . $c['class_name'] . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
