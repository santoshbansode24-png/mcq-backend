<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$response = [];

try {
    // Check Connection
    $response['db_connection'] = 'Success';
    
    // Check Table
    $stmt = $pdo->query("SHOW TABLES LIKE 'english_missions'");
    $tableExists = $stmt->rowCount() > 0;
    $response['table_exists'] = $tableExists;

    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM english_missions");
        $row = $stmt->fetch();
        $response['mission_count'] = $row['count'];
        
        $stmt = $pdo->query("SELECT * FROM english_missions LIMIT 1");
        $response['first_mission'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $response['error'] = "Table 'english_missions' does not exist.";
    }

} catch (PDOException $e) {
    $response['db_error'] = $e->getMessage();
} catch (Exception $e) {
    $response['general_error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
