<?php
/**
 * Advanced Schema Optimization for Veeru App
 * Adds missing performance indexes to the advanced schema tables.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU ADVANCED SCHEMA OPTIMIZATION ===\n\n";

$indexes = [
    // 1. classrooms
    "classrooms" => [
        "idx_teacher_id" => "ALTER TABLE `classrooms` ADD INDEX `idx_teacher_id` (`teacher_id`)"
    ],
    // 2. student_class_mapping
    "student_class_mapping" => [
        "idx_class_id" => "ALTER TABLE `student_class_mapping` ADD INDEX `idx_class_id` (`class_id`)"
    ],
    // 3. mcq_progress
    "mcq_progress" => [
        "idx_subject_id" => "ALTER TABLE `mcq_progress` ADD INDEX `idx_subject_id` (`subject_id`)",
        "idx_chapter_id" => "ALTER TABLE `mcq_progress` ADD INDEX `idx_chapter_id` (`chapter_id`)"
    ]
];

foreach ($indexes as $table => $index_queries) {
    echo "Optimizing table $table...\n";
    foreach ($index_queries as $index_name => $sql) {
        // Check if index exists first to prevent errors on multiple runs
        $check_sql = "SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'";
        try {
            $stmt = $pdo->query($check_sql);
            if ($stmt->rowCount() == 0) {
                echo "   Adding index $index_name... ";
                $pdo->exec($sql);
                echo "✅ OK\n";
            } else {
                echo "   Index $index_name already exists. Skipping.\n";
            }
        } catch (PDOException $e) {
            echo "❌ ERROR adding $index_name to $table: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nAdvanced Schema optimization completed successfully!\n";
?>
