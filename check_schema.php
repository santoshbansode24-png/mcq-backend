<?php
require_once 'backend/config/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $users_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "USERS TABLE:\n";
    print_r($users_cols);

    $stmt = $pdo->query("SHOW COLUMNS FROM teacher_classes");
    $tc_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTEACHER_CLASSES TABLE:\n";
    print_r($tc_cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
