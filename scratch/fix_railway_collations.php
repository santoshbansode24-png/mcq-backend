<?php
// Script to fix collation mismatch on Railway MySQL Database
$railway_host = 'yamanote.proxy.rlwy.net';
$railway_port = 24540;
$railway_user = 'root';
$railway_pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$railway_db = 'railway';

echo "Connecting to Railway MySQL...\n";
try {
    $dsn = "mysql:host=$railway_host;port=$railway_port;dbname=$railway_db;charset=utf8mb4";
    $pdo = new PDO($dsn, $railway_user, $railway_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Connected successfully!\n\n";

    // Convert database default collation
    echo "1. Normalizing Database default collation...\n";
    $pdo->exec("ALTER DATABASE `$railway_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   Database default set to utf8mb4_unicode_ci.\n\n";

    // List of tables to convert
    $tables = [
        'class_updates',
        'notifications',
        'users',
        'classrooms',
        'student_class_mapping',
        'messages',
        'live_exams',
        'class_exam_results',
        'mental_math_progress',
        'vocab_progress',
        'vocab_bookmarks',
        'mcq_progress',
        'chapter_completion',
        'classes',
        'subjects',
        'chapters',
        'mcqs',
        'notes'
    ];

    echo "2. Converting tables to utf8mb4_unicode_ci...\n";
    foreach ($tables as $table) {
        try {
            // Check if table exists
            $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($check) {
                echo "   Converting table '$table'... ";
                $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "✅ Success\n";
            } else {
                echo "   Table '$table' does not exist, skipping.\n";
            }
        } catch (PDOException $ex) {
            echo "   ❌ Failed to convert '$table': " . $ex->getMessage() . "\n";
        }
    }

    echo "\n🎉 DATABASE COLLATION REPAIR COMPLETED SUCCESSFULLY!\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
?>
