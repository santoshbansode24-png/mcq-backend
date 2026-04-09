<?php
require 'backend/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM study_tasks");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>
