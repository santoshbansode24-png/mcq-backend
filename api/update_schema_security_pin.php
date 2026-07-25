<?php
/**
 * Migration Script: Add security_pin column to users table
 * Veeru
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'security_pin'");
    if ($check->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "Column 'security_pin' already exists in users table."
        ]);
        exit();
    }

    // Add security_pin column
    $pdo->exec("ALTER TABLE users ADD COLUMN security_pin VARCHAR(4) DEFAULT NULL AFTER password");

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully added 'security_pin' column to users table!"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?>
