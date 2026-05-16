<?php
/**
 * Join Class API
 * Veeru App: Connect student to a class via 6-digit code
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$class_code = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';

if (!$user_id || !$class_code) {
    echo json_encode(['status' => 'error', 'message' => 'User ID and 6-digit code are required.']);
    exit;
}

try {
    // 1. Find class by code (Using a custom field 'join_code' which we might need to add or using class_id if code = ID)
    // For now, let's assume class_id is the code for simplicity, or look for a match in a new column.
    // OPTIMIZATION: We will check if 'join_code' exists, otherwise use class_id as fallback.
    
    // Check if classes table has join_code
    $checkCol = $pdo->query("SHOW COLUMNS FROM classes LIKE 'join_code'");
    $hasJoinCode = $checkCol->fetch();
    
    if (!$hasJoinCode) {
        // Add join_code column if missing
        $pdo->exec("ALTER TABLE classes ADD COLUMN join_code VARCHAR(10) UNIQUE AFTER class_name");
        // Seed some codes for existing classes
        $pdo->exec("UPDATE classes SET join_code = LPAD(class_id, 6, '0') WHERE join_code IS NULL");
    }

    $stmt = $pdo->prepare("SELECT class_id, class_name FROM classes WHERE join_code = ? OR (class_id = ? AND LENGTH(?) <= 6)");
    $stmt->execute([$class_code, $class_code, $class_code]);
    $class = $stmt->fetch();

    if (!$class) {
        throw new Exception("Invalid 6-digit class code. Please check with your teacher.");
    }

    // 2. Update User's class_id
    $update = $pdo->prepare("UPDATE users SET class_id = ? WHERE user_id = ?");
    $update->execute([$class['class_id'], $user_id]);

    if ($update->rowCount() === 0) {
        // Maybe user already has this class_id, that's fine
    }

    echo json_encode([
        'status' => 'success', 
        'message' => "Successfully joined " . $class['class_name'],
        'class_id' => $class['class_id'],
        'class_name' => $class['class_name']
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
