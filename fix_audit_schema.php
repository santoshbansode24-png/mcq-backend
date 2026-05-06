<?php
/**
 * Database Schema Update for Audit System
 */
require_once 'config/db.php';

echo "<h1>Updating Schema for Audit System...</h1>";

$tables = ['mcqs', 'flashcards', 'quick_revision'];

foreach ($tables as $table) {
    echo "Processing table: $table... ";
    try {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'status'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
            echo "Added 'status'. ";
        } else {
            echo "'status' exists. ";
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'admin_feedback'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN admin_feedback TEXT DEFAULT NULL");
            echo "Added 'admin_feedback'. ";
        } else {
            echo "'admin_feedback' exists. ";
        }
        echo "<span style='color:green'>OK</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
    }
}

echo "<h2>Schema Update Complete!</h2>";
?>
