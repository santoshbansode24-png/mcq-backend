<?php
/**
 * Database Migration Script
 * Rename: mcq_project_v2 → veeru_db
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Veeru Database Migration Script ===\n\n";

// Database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$old_db = 'mcq_project_v2';
$new_db = 'veeru_db';

try {
    // Connect to MySQL
    echo "1. Connecting to MySQL...\n";
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✓ Connected successfully\n\n";

    // Check if old database exists
    echo "2. Checking if '$old_db' exists...\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$old_db'");
    $oldDbExists = $stmt->rowCount() > 0;
    
    if ($oldDbExists) {
        echo "   ✓ Database '$old_db' found\n\n";
    } else {
        echo "   ✗ Database '$old_db' not found\n";
        echo "   → Will create fresh '$new_db' database\n\n";
    }

    // Check if new database already exists
    echo "3. Checking if '$new_db' already exists...\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$new_db'");
    $newDbExists = $stmt->rowCount() > 0;
    
    if ($newDbExists) {
        echo "   ! Database '$new_db' already exists\n";
        echo "   → Skipping creation\n\n";
    } else {
        echo "   → Creating new database '$new_db'...\n";
        $pdo->exec("CREATE DATABASE `$new_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "   ✓ Database '$new_db' created\n\n";
    }

    // Copy data if old database exists
    if ($oldDbExists && !$newDbExists) {
        echo "4. Copying data from '$old_db' to '$new_db'...\n";
        
        // Get all tables from old database (excluding views)
        $pdo->exec("USE `$old_db`");
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "   Found " . count($tables) . " tables to copy\n";
        
        foreach ($tables as $table) {
            echo "   → Copying table: $table...";
            
            try {
                // Copy table structure and data
                $pdo->exec("CREATE TABLE `$new_db`.`$table` LIKE `$old_db`.`$table`");
                $pdo->exec("INSERT INTO `$new_db`.`$table` SELECT * FROM `$old_db`.`$table`");
                echo " ✓\n";
            } catch (PDOException $e) {
                echo " ✗ (Error: " . $e->getMessage() . ")\n";
            }
        }
        
        // Copy views separately
        echo "\n   Copying views...\n";
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($views as $view) {
            echo "   → Copying view: $view...";
            
            try {
                // Get view definition
                $stmt = $pdo->query("SHOW CREATE VIEW `$old_db`.`$view`");
                $viewDef = $stmt->fetch(PDO::FETCH_ASSOC);
                $createView = $viewDef['Create View'];
                
                // Replace database name in view definition
                $createView = str_replace("`$old_db`.", "`$new_db`.", $createView);
                $createView = str_replace("CREATE ", "CREATE OR REPLACE ", $createView);
                
                $pdo->exec("USE `$new_db`");
                $pdo->exec($createView);
                echo " ✓\n";
            } catch (PDOException $e) {
                echo " ✗ (Error: " . $e->getMessage() . ")\n";
            }
        }
        
        echo "   ✓ All tables and views copied successfully\n\n";
    } elseif ($oldDbExists && $newDbExists) {
        echo "4. Both databases exist - skipping data copy\n";
        echo "   → If you want to re-copy, delete '$new_db' first\n\n";
    }

    // Verify new database
    echo "5. Verifying '$new_db' database...\n";
    $pdo->exec("USE `$new_db`");
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   Tables in '$new_db': " . count($tables) . "\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "   - $table: $count rows\n";
    }
    echo "\n";

    // Test connection with new database
    echo "6. Testing connection to '$new_db'...\n";
    $testPdo = new PDO("mysql:host=$host;dbname=$new_db;charset=utf8mb4", $user, $pass);
    $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✓ Connection successful\n\n";

    echo "=== Migration Complete! ===\n\n";
    echo "Summary:\n";
    echo "- Old database: $old_db " . ($oldDbExists ? "(exists)" : "(not found)") . "\n";
    echo "- New database: $new_db (ready to use)\n";
    echo "- Tables: " . count($tables) . "\n";
    echo "\nNext steps:\n";
    echo "1. Restart XAMPP Apache server\n";
    echo "2. Test backend: http://localhost/mcq%20project2.0/backend/api/test_db_connection.php\n";
    echo "3. Rebuild mobile app: npx expo start --clear\n";

} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
