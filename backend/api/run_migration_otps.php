<?php
require_once '../config/db.php';

try {
    echo "Running migration...\n";
    $sql = "ALTER TABLE password_reset_otps MODIFY phone_number VARCHAR(100) NOT NULL";
    $pdo->exec($sql);
    echo "Migration completed successfully: phone_number column updated to VARCHAR(100).\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
