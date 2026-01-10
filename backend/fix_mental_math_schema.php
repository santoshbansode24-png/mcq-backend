<?php
// backend/fix_mental_math_schema.php
include_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($pdo)) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

try {
    // 1. Check if table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'student_mental_math_progress'")->rowCount() > 0;

    if (!$table_exists) {
        // Create Table with UNIQUE constraint
        $sql = "CREATE TABLE `student_mental_math_progress` (
            `progress_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `level` INT DEFAULT 1,
            `total_sets_completed` INT DEFAULT 0,
            `total_correct_answers` INT DEFAULT 0,
            `last_played` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo json_encode(["status" => "success", "message" => "Table created with UNIQUE constraint."]);
    } else {
        // 2. Check if UNIQUE index exists on user_id
        // We'll try to add it. If it fails (duplicates exist), we might need to handle it.
        // But for now, let's assume we can add it safely or it already exists.
        
        // This query is safe to run even if index exists (using ADD UNIQUE IF NOT EXISTS logic isn't standard in generic MySQL without complexity, 
        // using try-catch to attempt adding it is simpler).
        
        try {
            // First drop generic index if it obscures the unique index (optional, skipping for safety)
            // Attempt to add unique index
            $pdo->exec("ALTER TABLE `student_mental_math_progress` ADD UNIQUE KEY `unique_user` (`user_id`)");
            echo json_encode(["status" => "success", "message" => "Added UNIQUE constraint to user_id."]);
        } catch (PDOException $e) {
            // Error 1061: Duplicate key name (already exists) -> Good
            // Error 1062: Duplicate entry (data conflict) -> Bad
            if (strpos($e->getMessage(), '1061') !== false) {
                 echo json_encode(["status" => "success", "message" => "UNIQUE constraint already exists."]);
            } else {
                 echo json_encode(["status" => "warning", "message" => "Could not add UNIQUE constraint: " . $e->getMessage()]);
            }
        }
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
