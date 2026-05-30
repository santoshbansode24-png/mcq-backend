<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$class_code = isset($_GET['class_code']) ? $_GET['class_code'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$with_user_id = isset($_GET['with_user_id']) ? (int)$_GET['with_user_id'] : null;

if (empty($class_code) && $user_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'class_code or user_id required']);
    exit;
}

try {
    if ($with_user_id !== null) {
        // 1-on-1 chat between user_id and with_user_id (in a specific class or globally)
        $query = "
            SELECT m.*, u.name as sender_name 
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.user_id
            WHERE (
                (m.sender_id = ? AND m.receiver_id = ?) 
                OR 
                (m.sender_id = ? AND m.receiver_id = ?)
                OR 
                (m.class_code = ? AND m.receiver_id IS NULL)
            )
        ";
        $params = [$user_id, $with_user_id, $with_user_id, $user_id, $class_code];
        
        if (!empty($class_code)) {
            $query .= " AND m.class_code = ?";
            $params[] = $class_code;
        }
        
        $query .= " ORDER BY m.created_at ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        // Broadcasts / Class-wide chat
        $query = "
            SELECT m.*, u.name as sender_name 
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.user_id
            WHERE m.class_code = ? AND m.receiver_id IS NULL
            ORDER BY m.created_at ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$class_code]);
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
}
?>
