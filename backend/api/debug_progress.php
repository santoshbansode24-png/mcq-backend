<?php
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM content_progress LIMIT 20");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Count: " . count($rows) . "\n";
    print_r($rows);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
