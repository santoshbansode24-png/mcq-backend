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
$user_id = 0;
$rawInput = file_get_contents("php://input");
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    $user_id = isset($decoded['user_id']) ? (int)$decoded['user_id'] : 0;
}

if ($user_id <= 0) {
    $user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
}

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing or invalid user_id"]);
    exit;
}

try {
    /** @var PDO $pdo */
    // Optimization: Only select necessary columns
    $stmt = $pdo->prepare("SELECT mental_math_level, abacus_level FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure we always return a valid level (never 0 or null)
    $mentalLevel = max(1, (int)($progress['mental_math_level'] ?? 1));
    $abacusLevel = max(1, (int)($progress['abacus_level'] ?? 1));

    echo json_encode([
        "status"            => "success",
        "mental_math_level" => $mentalLevel,
        "abacus_level"      => $abacusLevel
    ]);

} catch (PDOException $e) {
    // If table columns are missing, fallback to level 1 safely
    error_log("Math Progress Error: " . $e->getMessage());
    echo json_encode([
        "status"            => "success",
        "mental_math_level" => 1,
        "abacus_level"      => 1,
        "warning"           => "Fallback defaults used (Schema mismatch)"
    ]);
}
?>
