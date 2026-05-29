<?php
include_once '../config/db.php';

try {
    // Drop the old constraint pointing to the legacy teachers table
    $pdo->exec("ALTER TABLE class_updates DROP FOREIGN KEY class_updates_ibfk_1");
    
    // Add the new constraint pointing to the unified users table
    $pdo->exec("ALTER TABLE class_updates ADD CONSTRAINT class_updates_ibfk_1 FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE");
    
    echo "Database constraint fixed successfully on Production!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
