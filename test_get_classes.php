<?php
require 'config/db.php';
try {
    $stmt = $pdo->prepare("
        SELECT 
            cr.class_id,
            cr.class_name,
            tc.division_name,
            tc.class_code,
            COUNT(scm.student_id) as student_count
        FROM teacher_classes tc
        JOIN classrooms cr ON tc.class_code = cr.class_code
        LEFT JOIN student_class_mapping scm ON scm.class_id = cr.class_id
        WHERE tc.teacher_id = ?
        GROUP BY cr.class_id, cr.class_name, tc.division_name, tc.class_code
        ORDER BY cr.class_name ASC
    ");
    $stmt->execute([32]);
    echo "Query Success!\n";
    print_r($stmt->fetchAll());
} catch (PDOException $e) {
    echo "Query Error: " . $e->getMessage();
}
?>
