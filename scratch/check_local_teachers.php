<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("SELECT user_id, name, email, user_type, password FROM users WHERE LOWER(user_type) = 'teacher'");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($teachers) . " local teachers:\n";
    foreach ($teachers as $t) {
        echo "ID: {$t['user_id']} | Name: {$t['name']} | Email: {$t['email']} | Hash: {$t['password']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
