<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$sender_id = isset($input['sender_id']) ? (int)$input['sender_id'] : 0;
$class_code = isset($input['class_code']) ? $input['class_code'] : null;
$message_text = isset($input['message_text']) ? $input['message_text'] : '';
$receiver_id = isset($input['receiver_id']) && $input['receiver_id'] !== '' ? (int)$input['receiver_id'] : null;

if ($sender_id === 0 || empty($message_text)) {
    echo json_encode(['status' => 'error', 'message' => 'sender_id and message_text are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, class_code, message_text) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$sender_id, $receiver_id, $class_code, $message_text]);

    $message_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success', 
        'message' => 'Message sent',
        'data' => [
            'id' => $message_id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'class_code' => $class_code,
            'message_text' => $message_text,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error', 'details' => $e->getMessage()]);
}
?>
