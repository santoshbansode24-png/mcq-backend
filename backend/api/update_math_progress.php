<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db.php';

// Support both JSON POST and traditional GET/POST
$user_id   = 0;
$type      = '';
$new_level = 0;

$rawInput = file_get_contents("php://input");
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    $user_id   = isset($decoded['user_id'])   ? (int)$decoded['user_id']   : 0;
    $type      = isset($decoded['type'])      ? trim($decoded['type'])      : '';
    $new_level = isset($decoded['new_level']) ? (int)$decoded['new_level']  : 0;
}

// Fallback to traditional $_REQUEST if JSON is empty
if ($user_id <= 0) {
    $user_id   = isset($_REQUEST['user_id'])   ? (int)$_REQUEST['user_id']   : 0;
    $type      = isset($_REQUEST['type'])      ? trim($_REQUEST['type'])      : '';
    $new_level = isset($_REQUEST['new_level']) ? (int)$_REQUEST['new_level']  : 0;
}

// Input Validation
if ($user_id <= 0 || $new_level <= 0 || !in_array($type, ['classic', 'abacus'])) {
    echo json_encode(["status" => "error", "message" => "Missing or invalid parameters (user_id: $user_id, type: $type, level: $new_level)"]);
    exit;
}

// Cap to max level 30
$new_level = min($new_level, 30);

try {
    /** @var PDO $pdo */

    if ($type === 'classic') {
        // Only update if the new level is strictly greater (prevent downgrading)
        $stmt = $pdo->prepare(
            "UPDATE users 
             SET mental_math_level = ? 
             WHERE id = ? 
             AND (mental_math_level IS NULL OR mental_math_level < ?)"
        );
        $stmt->execute([$new_level, $user_id, $new_level]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users 
             SET abacus_level = ? 
             WHERE id = ? 
             AND (abacus_level IS NULL OR abacus_level < ?)"
        );
        $stmt->execute([$new_level, $user_id, $new_level]);
    }

    echo json_encode([
        "status"  => "success",
        "message" => "Progress updated",
        "rows"    => $stmt->rowCount() // 0 = already at this level or higher, 1 = updated
    ]);

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        // Schema not migrated yet — soft fail
        echo json_encode(["status" => "success", "message" => "Schema pending, ignored"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server error"]);
    }
}
?>
