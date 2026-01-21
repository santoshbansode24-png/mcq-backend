<?php
/**
 * Railway Migration Script - Add Medium Column to MCQs
 * 
 * Upload this file to Railway and access it via browser to run migration
 * URL: https://your-app.up.railway.app/backend/migrations/add_medium_column.php
 */

require_once '../config/db.php';

echo "<h1>Railway Database Migration - Add Medium Column</h1>";
echo "<pre>";

try {
    echo "Starting migration...\n\n";
    
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM mcqs LIKE 'medium'");
    if ($check->rowCount() > 0) {
        echo "⚠️  Column 'medium' already exists. Skipping...\n";
    } else {
        // Add medium column
        $pdo->exec("ALTER TABLE mcqs ADD COLUMN medium VARCHAR(20) DEFAULT 'english' AFTER difficulty");
        echo "✅ Added 'medium' column to mcqs table\n";
    }
    
    // Check if index exists
    $checkIndex = $pdo->query("SHOW INDEX FROM mcqs WHERE Key_name = 'idx_chapter_medium'");
    if ($checkIndex->rowCount() > 0) {
        echo "⚠️  Index 'idx_chapter_medium' already exists. Skipping...\n";
    } else {
        // Add index
        $pdo->exec("ALTER TABLE mcqs ADD INDEX idx_chapter_medium (chapter_id, medium)");
        echo "✅ Added index 'idx_chapter_medium'\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ MIGRATION COMPLETE!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Show table structure
    echo "Current mcqs table structure:\n\n";
    $stmt = $pdo->query("DESCRIBE mcqs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo sprintf("%-15s %-20s %s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Default'] ? "DEFAULT: " . $col['Default'] : ""
        );
    }
    
    // Count MCQs by medium
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "MCQ Count by Medium:\n\n";
    $count = $pdo->query("SELECT medium, COUNT(*) as count FROM mcqs GROUP BY medium");
    $results = $count->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        echo sprintf("%-15s: %d MCQs\n", ucfirst($row['medium']), $row['count']);
    }
    
    echo "\n✅ All existing MCQs are set to 'english' by default\n";
    echo "✅ You can now upload Marathi MCQs via admin panel\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nIf the column already exists, this is normal. Migration may have already run.\n";
}

echo "</pre>";
echo "<p><a href='../api/get_mcqs.php?chapter_id=15&medium=english'>Test API</a></p>";
?>
