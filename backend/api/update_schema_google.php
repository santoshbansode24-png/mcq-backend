<?php
/**
 * Database Schema Update Script (Google Auth)
 * Veeru
 */

require_once '../config/db.php';

echo "<h2>Database Schema Updater (Google Auth)</h2>";

function addColumnRequest($pdo, $table, $column, $type) {
    try {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        if ($check->rowCount() == 0) {
            // Add column
            $sql = "ALTER TABLE $table ADD COLUMN $column $type";
            $pdo->exec($sql);
            echo "<p style='color: green'>✅ Added column <strong>$column</strong> to table <strong>$table</strong>.</p>";
        } else {
            echo "<p style='color: orange'>⚠️ Column <strong>$column</strong> already exists in <strong>$table</strong>.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red'>❌ Error adding $column: " . $e->getMessage() . "</p>";
    }
}

// Add google_id
addColumnRequest($pdo, 'users', 'google_id', 'VARCHAR(255) DEFAULT NULL');

// Add profile_picture
addColumnRequest($pdo, 'users', 'profile_picture', 'VARCHAR(500) DEFAULT NULL');

echo "<p>Done.</p>";
?>
