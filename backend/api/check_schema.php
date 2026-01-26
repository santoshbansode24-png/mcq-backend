<?php
require_once __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE user_english_progress");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
