<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

$user_id = $data->user_id;

try {
    /** @var PDO $pdo */
    
    // Safety check just in case the columns aren't added yet (so app doesn't crash on older schema)
    // Try to select them
    $stmt = $pdo->prepare("SELECT mental_math_level, abacus_level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($progress) {
        echo json_encode([
            "status" => "success", 
            "mental_math_level" => (int)$progress['mental_math_level'],
            "abacus_level" => (int)$progress['abacus_level']
        ]);
    } else {
        // Fallback or user not found
        echo json_encode(["status" => "success", "mental_math_level" => 1, "abacus_level" => 1]);
    }

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        // Columns don't exist yet, return defaults safely
        echo json_encode(["status" => "success", "mental_math_level" => 1, "abacus_level" => 1]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>
