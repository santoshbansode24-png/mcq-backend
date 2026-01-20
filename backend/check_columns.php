<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE subjects");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
?>
