<?php
require 'config/db.php';
try {
    $migrateStmt = $pdo->prepare("
        INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level)
        SELECT 
            tc.teacher_id, 
            tc.class_code, 
            c.class_name, 
            COALESCE(u.board, 'State Board') as board, 
            COALESCE(u.medium, 'Marathi') as medium, 
            c.class_id as class_level
        FROM teacher_classes tc
        JOIN classes c ON tc.class_id = c.class_id
        JOIN users u ON tc.teacher_id = u.user_id
        WHERE tc.class_code IS NOT NULL 
          AND CONVERT(tc.class_code USING utf8mb4) COLLATE utf8mb4_unicode_ci NOT IN (SELECT CONVERT(class_code USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM classrooms)
    ");
    $migrateStmt->execute();
    echo "Migration Success!";
} catch (PDOException $e) {
    echo "Migration Error: " . $e->getMessage();
}
?>
