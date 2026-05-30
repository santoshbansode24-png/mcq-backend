<?php
require 'config/db.php';
try {
    $stmt = $pdo->prepare('SELECT cu.id as notification_id FROM class_updates cu LIMIT 1'); 
    $stmt->execute(); 
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
