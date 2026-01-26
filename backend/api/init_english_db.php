<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../sql/create_english_missions.sql');
    
    // Remove the CHECK constraint just in case it's causing syntax error on older MariaDB
    // $sql = str_replace("CHECK (json_valid(`target_vocab_json`))", "", $sql);
    
    $pdo->exec($sql);
    echo "Success: Table english_missions created and populated.\n";
    
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
?>
