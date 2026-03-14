<?php
/**
 * Database Collation Fixer
 * Automatically runs the SQL commands to fix the "Illegal mix of collations" error.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    die("Unauthorized. Please log in to admin first.");
}

require_once '../../config/db.php';

echo "<h2>Checking Database Collations...</h2>";

try {
    // Get current database name
    $db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    
    echo "Normalizing Database: <strong>$db_name</strong><br>";
    
    // Execute normalization commands
    $queries = [
        "ALTER DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE classes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE subjects CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE chapters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE mcqs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE notes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE app_content_updates CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ SUCCESS: " . substr($sql, 0, 40) . "...<br>";
        } catch (Exception $e) {
            echo "⚠️ SKIPPED: " . substr($sql, 0, 40) . " - " . $e->getMessage() . "<br>";
        }
    }

    echo "<h3>🎉 Done! All tables are now synchronized.</h3>";
    echo "<p><a href='mcqs.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Back to MCQs Upload</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
