<?php
require_once __DIR__ . '/../config/db.php';
try {
    $userId = 1;
    $levelId = 1;
    $fluencyScore = 100;
    
    $insertStmt = $pdo->prepare("INSERT INTO user_english_progress 
                        (user_id, level_id, is_completed, fluency_score, stars) 
                        VALUES (?, ?, 1, ?, 3) 
                        ON DUPLICATE KEY UPDATE 
                        is_completed = 1, 
                        fluency_score = GREATEST(fluency_score, VALUES(fluency_score))");
    $insertStmt->execute([$userId, $levelId, $fluencyScore]);
    echo "Success: Inserted/Updated record for user 1 level 1.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
