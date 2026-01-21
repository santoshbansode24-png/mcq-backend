<?php
/**
 * Simple Migration Runner for Railway
 * Access this file via: https://your-app.up.railway.app/run_migration.php
 */

// Include database connection
require_once 'backend/config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Railway Migration - Add Medium Column</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .info { color: #0af; }
        pre { background: #000; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🚀 Railway Database Migration</h1>
    <h2>Adding Medium Column to MCQs Table</h2>
    <hr>
    <pre>
<?php

try {
    echo "Starting migration...\n\n";
    
    // Check if column already exists
    echo "Checking if 'medium' column exists...\n";
    $check = $pdo->query("SHOW COLUMNS FROM mcqs LIKE 'medium'");
    
    if ($check->rowCount() > 0) {
        echo "<span class='info'>⚠️  Column 'medium' already exists. Skipping creation...</span>\n";
    } else {
        echo "Adding 'medium' column...\n";
        $pdo->exec("ALTER TABLE mcqs ADD COLUMN medium VARCHAR(20) DEFAULT 'english' AFTER difficulty");
        echo "<span class='success'>✅ Column 'medium' added successfully!</span>\n";
    }
    
    echo "\n";
    
    // Check if index exists
    echo "Checking if index exists...\n";
    $checkIndex = $pdo->query("SHOW INDEX FROM mcqs WHERE Key_name = 'idx_chapter_medium'");
    
    if ($checkIndex->rowCount() > 0) {
        echo "<span class='info'>⚠️  Index 'idx_chapter_medium' already exists. Skipping...</span>\n";
    } else {
        echo "Adding index...\n";
        $pdo->exec("ALTER TABLE mcqs ADD INDEX idx_chapter_medium (chapter_id, medium)");
        echo "<span class='success'>✅ Index 'idx_chapter_medium' created successfully!</span>\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "<span class='success'>✅ MIGRATION COMPLETED SUCCESSFULLY!</span>\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Show table structure
    echo "Current MCQs table structure:\n\n";
    $stmt = $pdo->query("DESCRIBE mcqs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-20s %-25s %-15s\n", "Field", "Type", "Default");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($columns as $col) {
        printf("%-20s %-25s %-15s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Default'] ?? 'NULL'
        );
    }
    
    // Count MCQs by medium
    echo "\n\n" . str_repeat("=", 60) . "\n";
    echo "MCQ Statistics by Medium:\n\n";
    
    $count = $pdo->query("SELECT medium, COUNT(*) as count FROM mcqs GROUP BY medium");
    $results = $count->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        printf("%-15s: %d MCQs\n", ucfirst($row['medium']), $row['count']);
    }
    
    echo "\n<span class='success'>✅ All existing MCQs are set to 'english' by default</span>\n";
    echo "<span class='success'>✅ You can now upload Marathi MCQs via admin panel</span>\n";
    
    echo "\n\n" . str_repeat("=", 60) . "\n";
    echo "Next Steps:\n";
    echo "1. Test Admin Panel: <a href='/admin/mcqs.php' style='color:#0af'>/admin/mcqs.php</a>\n";
    echo "2. Test API: <a href='/backend/api/get_mcqs.php?chapter_id=15&medium=english' style='color:#0af'>/backend/api/get_mcqs.php?chapter_id=15&medium=english</a>\n";
    
} catch (PDOException $e) {
    echo "\n<span class='error'>❌ ERROR: " . $e->getMessage() . "</span>\n";
    echo "\nIf the error says 'Duplicate column name', the migration already ran successfully.\n";
}

?>
    </pre>
    <hr>
    <p><strong>Migration script completed.</strong></p>
</body>
</html>
