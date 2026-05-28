<?php
require_once 'config/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('class_id', $cols) || in_array('teacher_id', $cols)) {
            echo "- $table\n";
        }
    } catch (PDOException $e) {}
}
?>
