<?php
/**
 * Comprehensive Database Audit for Railway Production
 */

$host = 'yamanote.proxy.rlwy.net';
$port = 24540;
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$dbname = 'railway';

echo "--- CONNECTING TO RAILWAY DATABASE ---\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    echo "✅ Connection Successful!\n\n";

    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "--- TABLES FOUND IN DATABASE: " . count($tables) . " ---\n";
    foreach ($tables as $table) {
        // Get row count
        $cntStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $cntStmt->fetchColumn();
        echo "- $table ($count rows)\n";
    }
    echo "\n";

    // Core tables to audit schema
    $core_tables = [
        'users', 'classrooms', 'teacher_classes', 'class_updates', 'live_exams', 
        'student_class_mapping', 'student_exam_attempts', 'mcq_attempts',
        'classes', 'subjects', 'chapters', 'mcqs', 'flashcards', 'quick_revisions', 
        'notes', 'vocabulary', 'vocab_attempts', 'vocab_favorites', 'study_tasks',
        'notifications', 'exam_schedules'
    ];

    echo "--- DETAILED SCHEMA AUDIT ---\n";
    foreach ($core_tables as $table) {
        if (in_array($table, $tables)) {
            echo "\nChecking Table: [$table]\n";
            try {
                $desc = $pdo->query("DESCRIBE `$table`")->fetchAll();
                foreach ($desc as $col) {
                    echo "  - {$col['Field']} ({$col['Type']}) - Null: {$col['Null']}, Key: {$col['Key']}, Default: " . json_encode($col['Default']) . "\n";
                }
            } catch (Exception $ex) {
                echo "  ❌ Failed to describe: " . $ex->getMessage() . "\n";
            }
        } else {
            echo "\n❌ Table [$table] is MISSING in the database!\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
?>
