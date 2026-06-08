<?php
require 'backend/config/db.php';
try {
    $stmt = $pdo->query("SELECT update_id, update_type, title, payload FROM class_updates ORDER BY update_id DESC LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "ID: " . $row['update_id'] . "\n";
        echo "Type: " . $row['update_type'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Payload: " . $row['payload'] . "\n";
        echo "-----------------------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
