<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/db.php';

try {
    // Exclude free trial (plan_id=1, or price=0) from premium purchase screen
    $stmt = $pdo->prepare("SELECT plan_id, plan_name, price, duration_days, description, features FROM subscriptions WHERE is_active = 1 AND price > 0 ORDER BY price ASC");
    $stmt->execute();
    
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process features into an array for easier frontend parsing
    foreach ($plans as &$plan) {
        if (!empty($plan['features'])) {
            $plan['features_list'] = array_map('trim', explode(',', $plan['features']));
        } else {
            $plan['features_list'] = [];
        }
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $plans
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
