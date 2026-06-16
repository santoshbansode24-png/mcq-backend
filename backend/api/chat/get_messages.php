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

function createMessagesTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `messages` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `sender_id` INT NOT NULL,
          `receiver_id` INT DEFAULT NULL,
          `class_code` VARCHAR(20) DEFAULT NULL,
          `message_text` TEXT NOT NULL,
          `attachment_url` VARCHAR(500) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_sender (sender_id),
          INDEX idx_receiver (receiver_id),
          INDEX idx_class_code (class_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function queryMessages($pdo, $class_code, $user_id, $with_user_id) {
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $messages = queryMessages($pdo, $class_code, $user_id, $with_user_id);
    echo json_encode(['status' => 'success', 'data' => $messages]);
} catch (PDOException $e) {
    // Check if table 'messages' doesn't exist
    if ($e->getCode() == '42S02' || strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        try {
            createMessagesTable($pdo);
            // Table was created, query again (should be empty now)
            $messages = queryMessages($pdo, $class_code, $user_id, $with_user_id);
            echo json_encode(['status' => 'success', 'data' => $messages]);
        } catch (PDOException $ex) {
            echo json_encode(['status' => 'error', 'message' => 'Database error during retry', 'details' => $ex->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
    }
}
?>
