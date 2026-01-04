<?php
require_once '../config/db.php';
try {
    $stmt = $pdo->query("SELECT * FROM classes");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($classes) . "\n";
    print_r($classes);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
