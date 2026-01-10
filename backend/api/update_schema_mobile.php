<?php
/**
 * Database Schema Update Script (Mobile)
 * Veeru
 */

require_once '../config/db.php';

echo "<h2>Database Schema Updater (Mobile)</h2>";

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

// Add mobile number
addColumnRequest($pdo, 'users', 'mobile', 'VARCHAR(20) DEFAULT NULL');

echo "<p>Done.</p>";
?>
