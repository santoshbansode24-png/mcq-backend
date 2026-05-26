<?php
require_once __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT c.class_id, c.teacher_id, c.class_name, c.board, c.medium, c.class_level, u.school_name, u.name as teacher_name
        FROM classrooms c
        JOIN users u ON c.teacher_id = u.user_id
        WHERE c.class_code = ?
    ");
    $stmt->execute(['VEERU1']);
    $classroom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Classroom query success!\n";
    print_r($classroom);

    if ($classroom) {
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET assigned_teacher_id = ?, 
                school_name = ?,
                board = ?,
                medium = ?,
                class_level = ?
            WHERE user_id = ? AND user_type = 'student'
        ");
        
        $updateStmt->execute([
            $classroom['teacher_id'],
            $classroom['school_name'],
            $classroom['board'],
            $classroom['medium'],
            $classroom['class_level'],
            8 // Assuming student ID 8 exists
        ]);
        echo "User update success!\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
