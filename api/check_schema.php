<?php
require_once '../config/db.php';
try {
    $tables = ['users', 'chapters'];
    foreach($tables as $t) {
        echo "DESCRIBE $t:\n";
        $stmt = $pdo->query("DESCRIBE $t");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch(Exception $e) { echo $e->getMessage(); }
?>
