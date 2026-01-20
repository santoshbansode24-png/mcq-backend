<?php
require_once 'config/db.php';

try {
    $sql = file_get_contents('seed_dummy_mcqs.sql');
    $pdo->exec($sql);
    echo "Dummy MCQs inserted successfully into Chapter 92 (Series Completion).";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
