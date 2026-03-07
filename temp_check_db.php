<?php
require_once 'c:/xampp/htdocs/veeru/backend/config/db.php';
try {
    $stmt = $pdo->query('SHOW TABLES');
    echo implode(", ", $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
