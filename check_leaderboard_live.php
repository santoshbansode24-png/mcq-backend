<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/db.php';
try {
    echo "USERS SCHEMA:\n";
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    print_r($stmt->fetch());

    echo "\n\nMCQ SCORES SCHEMA:\n";
    $stmt = $pdo->query("SHOW CREATE TABLE mcq_scores");
    print_r($stmt->fetch());
    
    echo "\n\nRUNNING LEADERBOARD QUERY:\n";
    $class_id = 10;
    $query = "SELECT 
                u.user_id as id, 
                u.name as full_name, 
                SUM(ms.score) as total_score,
                COUNT(ms.id) as tests_taken
              FROM users u
              JOIN mcq_scores ms ON u.user_id = ms.user_id
              WHERE u.class_id = ? AND u.user_type = 'student'
              GROUP BY u.user_id
              ORDER BY total_score DESC
              LIMIT 50";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$class_id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
