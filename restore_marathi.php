<?php
include_once __DIR__ . '/config/db.php';
global $pdo;

echo "Restoring Marathi words...\n";
$sql = file_get_contents(__DIR__ . '/add_easy_words_with_marathi.sql');

try {
    $pdo->exec($sql);
    echo "Success!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
