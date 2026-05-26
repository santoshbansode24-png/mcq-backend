<?php
require 'backend/config/db.php';

try {
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'youtube_refresh_token'");
    $col1 = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'youtube_channel_id'");
    $col2 = $stmt->fetch();
    
    if (!$col1) {
        $pdo->exec("ALTER TABLE users ADD COLUMN youtube_refresh_token TEXT DEFAULT NULL");
        echo "Added youtube_refresh_token column successfully.\n";
    } else {
        echo "youtube_refresh_token column already exists.\n";
    }
    
    if (!$col2) {
        $pdo->exec("ALTER TABLE users ADD COLUMN youtube_channel_id VARCHAR(255) DEFAULT NULL");
        echo "Added youtube_channel_id column successfully.\n";
    } else {
        echo "youtube_channel_id column already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
