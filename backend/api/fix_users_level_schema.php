<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU DB OPTIMIZATION ===\n\n";

$repairs = [
    "users" => [
        "mental_math_level" => "ALTER TABLE `users` ADD COLUMN `mental_math_level` INT DEFAULT 1",
        "abacus_level" => "ALTER TABLE `users` ADD COLUMN `abacus_level` INT DEFAULT 1"
    ]
];

foreach ($repairs as $table => $columns) {
    foreach ($columns as $col => $alter_sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
            if (!$check) {
                echo "Adding missing column $col to $table... ";
                $pdo->exec($alter_sql);
                echo "✅ Fixed\n";
            } else {
                echo "Column $col already exists in $table.\n";
            }
        } catch (Exception $e) {
            echo "❌ Fail updating $table.$col: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nOptimization completed successfully!";
?>
