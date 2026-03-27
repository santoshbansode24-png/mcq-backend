<?php
require_once '../config/db.php';

try {
    $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    echo "Successfully added chapter_ids column to study_tasks.";
} catch (PDOException $e) {
    if ($e->getCode() != '42S21' && strpos($e->getMessage(), '1060') === false) {
        echo "Error: " . $e->getMessage();
    } else {
        echo "Column already exists, you are good to go!";
    }
}
?>
