<?php
require_once 'config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/create_badges_tables.sql');
    if (!$sql) {
        die("Error: Could not read sql file.");
    }
    
    // Execute multiple queries
    $pdo->exec($sql);
    
    echo "Badges tables created successfully.";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>
