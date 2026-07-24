<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id === 0) {
    sendResponse('error', 'student_id required', null, 400);
}

try {
    // Return unique joined classrooms for this student with MAX(joined_at) in SELECT list for MySQL 3065 compliance
    $query = "
        SELECT 
            c.class_id, 
            c.class_name, 
            c.class_code, 
            c.board, 
            c.medium, 
            COALESCE(u.name, 'Teacher') as teacher_name,
            MAX(scm.joined_at) as joined_at
        FROM student_class_mapping scm
        JOIN classrooms c ON (scm.class_id = c.class_id OR scm.class_id = c.class_level)
        LEFT JOIN users u ON c.teacher_id = u.user_id
        WHERE scm.student_id = ?
        GROUP BY c.class_id, c.class_name, c.class_code, c.board, c.medium, u.name
        ORDER BY joined_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$student_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Joined classes fetched successfully', $classes, 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
