<?php
require_once '../config/db.php';

try {
    echo "<h2>Attempting to fix 'classes' table schema...</h2>";

    // 1. Check existing indexes
    $stmt = $pdo->query("SHOW INDEX FROM classes");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Current Indexes:</h3><ul>";
    $uniqueIndexName = '';
    foreach ($indexes as $idx) {
        echo "<li>" . $idx['Key_name'] . " (Column: " . $idx['Column_name'] . ", Unique: " . ($idx['Non_unique'] == 0 ? 'Yes' : 'No') . ")</li>";
        
        // Identify the problematic unique index on class_name
        if ($idx['Column_name'] == 'class_name' && $idx['Non_unique'] == 0 && $idx['Key_name'] != 'PRIMARY') {
            $uniqueIndexName = $idx['Key_name'];
        }
    }
    echo "</ul>";

    if ($uniqueIndexName) {
        echo "<p>Found unique constraint on 'class_name'. Index Name: <strong>$uniqueIndexName</strong></p>";
        
        // 2. Drop the old unique index
        $sqlDrop = "ALTER TABLE classes DROP INDEX $uniqueIndexName";
        $pdo->exec($sqlDrop);
        echo "<p style='color:green'>Dropped old unique index successfully.</p>";

        // 3. Add new composite unique index
        $sqlAdd = "ALTER TABLE classes ADD UNIQUE INDEX unique_class_board (class_name, board_type)";
        $pdo->exec($sqlAdd);
        echo "<p style='color:green'>Added new composite unique index (class_name + board_type) successfully.</p>";
        
    } else {
        echo "<p style='color:orange'>No simple unique index found on 'class_name' alone. Detecting if composite index already exists...</p>";
        
        // Check if our new index already exists to avoid error
        $hasComposite = false;
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] == 'unique_class_board') {
                $hasComposite = true;
                break;
            }
        }

        if (!$hasComposite) {
             echo "<p>Composite index not found. Attempting to add it anyway...</p>";
             try {
                $sqlAdd = "ALTER TABLE classes ADD UNIQUE INDEX unique_class_board (class_name, board_type)";
                $pdo->exec($sqlAdd);
                echo "<p style='color:green'>Added new composite unique index (class_name + board_type) successfully.</p>";
             } catch (Exception $e) {
                 echo "<p style='color:red'>Failed to add index: " . $e->getMessage() . "</p>";
             }
        } else {
            echo "<p style='color:green'>Composite unique index 'unique_class_board' already exists. You should be good to go!</p>";
        }
    }

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>
