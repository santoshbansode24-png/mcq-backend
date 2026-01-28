<?php
require_once 'cors_middleware.php';
require_once '../config/db.php';

try {
    $stmt = $pdo->query("DESCRIBE content_progress");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Table 'content_progress' exists.\nColumns:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
