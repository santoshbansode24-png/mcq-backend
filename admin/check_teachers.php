<?php
require_once '../config/db.php';
try {
    $stmt = $pdo->query("SELECT user_id, name, email, password, user_type FROM users WHERE user_type = 'teacher'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($users) . " teachers:\n";
    foreach ($users as $u) {
        echo "ID: " . $u['user_id'] . " | Name: " . $u['name'] . " | Email: " . $u['email'] . " | Password (Hash): " . substr($u['password'], 0, 20) . "...\n";
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
