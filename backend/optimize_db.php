<?php
require_once 'config/db.php';

try {
    echo "Checking indexes...\n";

    // multiple indexes check
    $indexes = ['videos' => 'chapter_id', 'notes' => 'chapter_id', 'mcqs' => 'chapter_id'];

    foreach ($indexes as $table => $column) {
        $stmt = $pdo->prepare("SHOW INDEX FROM $table WHERE Column_name = ?");
        $stmt->execute([$column]);

        if ($stmt->rowCount() == 0) {
            echo "Adding index to $table($column)...\n";
            $pdo->exec("ALTER TABLE $table ADD INDEX idx_{$column} ($column)");
            echo "Index added to $table.\n";
        } else {
            echo "Index already exists on $table($column).\n";
        }
    }

    echo "Optimization complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
