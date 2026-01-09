<?php
/**
 * Database Schema Update Script
 * Veeru
 */

require_once '../config/db.php';

echo "<h2>Database Schema Updater</h2>";

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

// Add school_name
addColumnRequest($pdo, 'users', 'school_name', 'VARCHAR(255) DEFAULT NULL');

// Add board
addColumnRequest($pdo, 'users', 'board', "ENUM('CBSE', 'State Board') DEFAULT NULL");

echo "<p>Done.</p>";
?>
