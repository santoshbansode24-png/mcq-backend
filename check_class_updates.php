<?php
require 'backend/config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE classrooms");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR classrooms: " . $e->getMessage() . "\n";
}
try {
    $stmt = $pdo->query("DESCRIBE teacher_classes");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR teacher_classes: " . $e->getMessage() . "\n";
}
?>
