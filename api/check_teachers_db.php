<?php
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SELECT user_id, name, email, user_type, school_name, created_at FROM users WHERE LOWER(user_type) = 'teacher'");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'count' => count($teachers),
        'teachers' => $teachers
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
