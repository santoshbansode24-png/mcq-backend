<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Drop the incorrect table
    $pdo->exec("DROP TABLE IF EXISTS ai_tasks");

    // Recreate with correct schema
    $pdo->exec("CREATE TABLE ai_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_type VARCHAR(50) NOT NULL,
        status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
        request_payload TEXT,
        result_data MEDIUMTEXT,
        error_message TEXT,
        progress INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "<h1>✅ AI Tasks Table Fixed Successfully!</h1>";
} catch (Exception $e) {
    echo "<h1>❌ Error: " . $e->getMessage() . "</h1>";
}
?>
