<?php
/**
 * Manual Set Unlock Script
 * Run this to unlock Set 2 for testing
 */

require_once './config/db.php';

try {
    $userId = 4; // Change this to your user ID
    
    // Update user stats to unlock Set 2
    $sql = "UPDATE user_vocab_stats 
            SET current_set = 2,
                highest_set_unlocked = 2,
                sets_completed = 1
            WHERE user_id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    
    // Check if update was successful
    $sql = "SELECT current_set, highest_set_unlocked, sets_completed 
            FROM user_vocab_stats 
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Set 2 Unlocked Successfully!\n\n";
    echo "User Stats:\n";
    echo "- Current Set: " . $stats['current_set'] . "\n";
    echo "- Highest Set Unlocked: " . $stats['highest_set_unlocked'] . "\n";
    echo "- Sets Completed: " . $stats['sets_completed'] . "\n";
    echo "\n✅ You can now access Set 2 in the app!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
