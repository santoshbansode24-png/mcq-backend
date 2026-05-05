<?php
require_once 'backend/config/db.php';

try {
    // 1. Create schools table
    $pdo->exec("CREATE TABLE IF NOT EXISTS schools (
        school_id INT AUTO_INCREMENT PRIMARY KEY,
        school_code VARCHAR(10) NOT NULL UNIQUE,
        school_name VARCHAR(255) NOT NULL,
        board_type VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Schools table created.\n";

    // 2. Add school_id to users
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT DEFAULT NULL");
        echo "Added school_id to users.\n";
    } catch (PDOException $e) {
        echo "school_id likely already exists. Error: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
