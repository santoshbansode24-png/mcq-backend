<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../sql/create_user_english_progress.sql');
    $pdo->exec($sql);
    echo "Success: Table user_english_progress created.\n";
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
?>
