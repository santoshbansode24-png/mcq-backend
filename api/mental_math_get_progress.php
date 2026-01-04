 <?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 1. Enforce GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    try {
        $query = "SELECT * FROM student_mental_math_progress WHERE user_id = :user_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // OPTIMIZATION: Cast numbers to Integers so React Native handles them correctly
            // (DB often returns them as strings like "5")
            $row['level'] = (int)$row['level'];
            $row['total_sets_completed'] = (int)$row['total_sets_completed'];
            $row['total_correct_answers'] = (int)$row['total_correct_answers'];

            echo json_encode(array("status" => "success", "data" => $row));
        } else {
            // New user - Return default integers
            echo json_encode(array(
                "status" => "success",
                "data" => array(
                    "level" => 1,
                    "total_sets_completed" => 0,
                    "total_correct_answers" => 0
                )
            ));
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Database error: " . $e->getMessage()));
    }

} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "User ID missing."));
}
?>