<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $tables);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
