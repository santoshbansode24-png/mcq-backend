<?php
/**
 * System Audit Script - Veeru Teacher Portal
 */
require_once 'config/db.php';

echo "<h1>Veeru System Audit Report</h1>";
echo "<pre>";

function checkTable($pdo, $tableName, $requiredColumns) {
    echo "Checking Table: [$tableName]... ";
    try {
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missing = array_diff($requiredColumns, $columns);
        if (empty($missing)) {
            echo "<span style='color:green'>OK</span>\n";
        } else {
            echo "<span style='color:red'>MISSING COLUMNS: " . implode(', ', $missing) . "</span>\n";
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>FAILED (Table might not exist: " . $e->getMessage() . ")</span>\n";
    }
}

// 1. Database Audit
echo "<h2>1. Database Schema</h2>";
checkTable($pdo, 'users', ['user_id', 'email', 'password', 'user_type', 'school_name', 'mobile', 'phone']);
checkTable($pdo, 'teacher_classes', ['id', 'teacher_id', 'class_id', 'class_code', 'division_name']);
checkTable($pdo, 'class_updates', ['id', 'teacher_id', 'class_id', 'update_type', 'title', 'message', 'payload']);
checkTable($pdo, 'live_exams', ['id', 'teacher_id', 'class_id', 'chapter_id', 'status']);

// 2. File Check
echo "<h2>2. Critical API Files</h2>";
$files = [
    'api/teacher_login.php',
    'api/get_notifications.php',
    'api/fix_teacher_schema.php',
    'backend/api/teacher/create_classroom.php',
    'backend/api/teacher/upload_class_material.php'
];

foreach ($files as $file) {
    echo "Checking File: [$file]... ";
    if (file_exists($file)) {
        echo "<span style='color:green'>EXISTS</span>\n";
    } else {
        echo "<span style='color:red'>NOT FOUND</span>\n";
    }
}

// 3. Helper Functions Audit
echo "<h2>3. Config Helpers</h2>";
echo "getJsonInput(): " . (function_exists('getJsonInput') ? "OK" : "MISSING") . "\n";
echo "validateRequired(): " . (function_exists('validateRequired') ? "OK" : "MISSING") . "\n";
echo "sendResponse(): " . (function_exists('sendResponse') ? "OK" : "MISSING") . "\n";

echo "</pre>";
?>
