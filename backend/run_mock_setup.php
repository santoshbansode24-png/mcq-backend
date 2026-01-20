<?php
require_once 'config/db.php';

try {
    $sql = file_get_contents('setup_mock_tests.sql');
    $pdo->exec($sql);
    echo "Mock Tests subject and chapters created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
