<?php
/**
 * Database Migration Script: Separate Unique Index per Email + User Type
 * Veeru
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

try {
    // 1. Drop existing single-column unique index on email if present
    try {
        $pdo->exec("ALTER TABLE users DROP INDEX email");
    } catch (Exception $e) {
        // Index might already be dropped
    }

    // 2. Add composite unique index on (email, user_type)
    try {
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY email_user_type (email, user_type)");
    } catch (Exception $e) {
        // Index might already exist
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Database schema updated successfully: Unique constraint is now per (email, user_type).'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
