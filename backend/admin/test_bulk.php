<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../config/db.php';
try {
    echo "<h2>Table Collations</h2>";
    $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
    echo "<pre>" . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "</pre>";

    echo "<h2>Column Collations (mcqs)</h2>";
    $stmt = $pdo->query("SELECT COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mcqs'");
    echo "<pre>" . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h2>Connection Details</h2>";
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'collation%'");
    echo "<pre>" . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "</pre>";
} catch (Exception $e) {
    echo $e->getMessage();
}
