<?php
require 'backend/config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE live_exams");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR live_exams: " . $e->getMessage() . "\n";
}
?>
