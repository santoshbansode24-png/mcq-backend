<?php
require_once 'config/db.php';

echo "<h2>Migrating Database for School Subscriptions...</h2>\n";

try {
    // 1. Create school_subscriptions table
    $sql = "CREATE TABLE IF NOT EXISTS school_subscriptions (
        school_id INT AUTO_INCREMENT PRIMARY KEY,
        school_name VARCHAR(255) NOT NULL,
        access_code VARCHAR(50) NOT NULL UNIQUE,
        valid_until DATE NOT NULL,
        max_teachers INT DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_access_code (access_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "✅ Table 'school_subscriptions' created.\n";

    // 2. Add school_id to users table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT DEFAULT NULL");
        echo "✅ Column 'school_id' added to users table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠️ Column 'school_id' already exists in users.\n";
        } else {
            throw $e;
        }
    }

    // 3. Add foreign key (Optional but good practice)
    try {
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_user_school FOREIGN KEY (school_id) REFERENCES school_subscriptions(school_id) ON DELETE SET NULL");
        echo "✅ Foreign key constraint added to users.school_id.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ Foreign key 'fk_user_school' already exists.\n";
        } else {
            echo "⚠️ Could not add foreign key (maybe existing data conflict): " . $e->getMessage() . "\n";
        }
    }

    echo "\n<h3>Migration Complete.</h3>\n";

} catch (PDOException $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
}
?>
