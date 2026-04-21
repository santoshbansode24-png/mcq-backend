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

$data = json_decode(file_get_contents("php://input"));

$user_id = isset($data->user_id) ? (int)$data->user_id : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing or invalid user_id"]);
    exit;
}

try {
    /** @var PDO $pdo */
    $stmt = $pdo->prepare("SELECT mental_math_level, abacus_level FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($progress) {
        // Ensure levels are never 0 or null — always at least 1
        echo json_encode([
            "status"            => "success",
            "mental_math_level" => max(1, (int)$progress['mental_math_level']),
            "abacus_level"      => max(1, (int)$progress['abacus_level'])
        ]);
    } else {
        // User not found — return safe defaults
        echo json_encode(["status" => "success", "mental_math_level" => 1, "abacus_level" => 1]);
    }

} catch (PDOException $e) {
    // If columns don't exist yet, return defaults so app never crashes
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo json_encode(["status" => "success", "mental_math_level" => 1, "abacus_level" => 1]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server error"]);
    }
}
?>
