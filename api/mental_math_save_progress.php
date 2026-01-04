<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => "error", "message" => "Method not allowed."));
    exit;
}

include_once '../config/db.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Database connection failed."));
    exit;
}

$db = $pdo;
$data = json_decode(file_get_contents("php://input"));

// 2. Validate Inputs
if (!empty($data->user_id) && isset($data->level) && isset($data->score)) {
    
    $user_id = $data->user_id;
    // Optimization: Force Integer types for safety
    $level   = (int)$data->level;
    $score   = (int)$data->score;

    try {
        // 3. OPTIMIZATION: Logic Update for "Downgrade Protection"
        // We use GREATEST() in the update clause.
        // If DB has Level 10, and we send Level 2, GREATEST(10, 2) keeps 10.
        
        $query = "INSERT INTO student_mental_math_progress 
                    (user_id, level, total_sets_completed, total_correct_answers, last_played)
                  VALUES 
                    (:user_id, :level, 1, :score, NOW())
                  ON DUPLICATE KEY UPDATE 
                    level = GREATEST(level, VALUES(level)), 
                    total_sets_completed = total_sets_completed + 1,
                    total_correct_answers = total_correct_answers + VALUES(total_correct_answers),
                    last_played = NOW()";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":level", $level);
        $stmt->bindParam(":score", $score);

        if ($stmt->execute()) {
            echo json_encode(array("status" => "success", "message" => "Progress saved successfully."));
        } else {
            throw new Exception("Query execution failed.");
        }

    } catch (Exception $e) {
        http_response_code(500);
        // Log error internally if possible, send generic msg to user
        echo json_encode(array("status" => "error", "message" => "Database error: " . $e->getMessage()));
    }

} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Incomplete data."));
}
?>