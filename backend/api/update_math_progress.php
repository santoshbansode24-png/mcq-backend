<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->type) || !isset($data->new_level)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$user_id = $data->user_id;
$type = $data->type; // 'classic' or 'abacus'
$new_level = (int)$data->new_level;

try {
    /** @var PDO $pdo */
    
    if ($type === 'classic') {
        // Only update if new level is higher to prevent downgrading
        $stmt = $pdo->prepare("UPDATE users SET mental_math_level = ? WHERE id = ? AND (mental_math_level IS NULL OR mental_math_level < ?)");
        $stmt->execute([$new_level, $user_id, $new_level]);
    } else if ($type === 'abacus') {
        $stmt = $pdo->prepare("UPDATE users SET abacus_level = ? WHERE id = ? AND (abacus_level IS NULL OR abacus_level < ?)");
        $stmt->execute([$new_level, $user_id, $new_level]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid type"]);
        exit;
    }

    echo json_encode(["status" => "success", "message" => "Progress updated successfully"]);

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        // Schema not updated yet, soft fail success
         echo json_encode(["status" => "success", "message" => "Schema not ready, ignored"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>
