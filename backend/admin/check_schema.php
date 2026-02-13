<?php
/**
 * Check Database Schema
 * Shows the actual structure of key tables
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

echo "=== DATABASE SCHEMA CHECK ===\n\n";

try {
    // Check tables exist
    $tables = ['boards', 'classes', 'subjects', 'chapters', 'mcqs', 'flashcards'];
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        echo str_repeat('-', 50) . "\n";
        
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            echo "  {$col['Field']} ({$col['Type']})";
            if ($col['Key'] === 'PRI') echo " [PRIMARY KEY]";
            if ($col['Key'] === 'MUL') echo " [FOREIGN KEY]";
            echo "\n";
        }
        
        // Show row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "  Total rows: $count\n";
        
        echo "\n";
    }
    
    // Show sample data from boards
    echo "=== SAMPLE DATA ===\n\n";
    
    echo "Boards:\n";
    $stmt = $pdo->query("SELECT * FROM boards LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  " . json_encode($row) . "\n";
    }
    
    echo "\nClasses:\n";
    $stmt = $pdo->query("SELECT * FROM classes LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  " . json_encode($row) . "\n";
    }
    
    echo "\nSubjects (first 5):\n";
    $stmt = $pdo->query("SELECT * FROM subjects LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  " . json_encode($row) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
