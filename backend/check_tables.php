<?php
require_once __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
