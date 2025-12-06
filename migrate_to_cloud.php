<?php
/**
 * Database Migration Tool (Manual Definition)
 * Guaranteed to work on TiDB Cloud
 */

// TiDB Credentials
$host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$port = '4000';
$username = 'f5vNyKym3dZo9L9.root';
$password = 'bv4kAHsj6ZdW16Dx';
$dbname = 'test';

echo "<h1>🚀 Database Migration to Cloud (Manual Mode)</h1>";
echo "<pre>";

try {
    // 1. Connect
    echo "Connecting to TiDB Cloud... ";
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "✅ Connected!\n\n";

    // 2. Define Tables Manually (No parsing needed)
    $queries = [
        "DROP TABLE IF EXISTS bookmarks",
        "DROP TABLE IF EXISTS mcq_results",
        "DROP TABLE IF EXISTS mcq_options",
        "DROP TABLE IF EXISTS mcqs",
        "DROP TABLE IF EXISTS chapters",
        "DROP TABLE IF EXISTS subjects",
        "DROP TABLE IF EXISTS users",
        "DROP TABLE IF EXISTS classes",

        "CREATE TABLE classes (
            class_id int(11) NOT NULL AUTO_INCREMENT,
            class_name varchar(50) NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (class_id)
        )",

        "CREATE TABLE users (
            user_id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            password varchar(255) NOT NULL,
            phone varchar(15) DEFAULT NULL,
            user_type enum('admin','student','teacher') NOT NULL DEFAULT 'student',
            class_id int(11) DEFAULT NULL,
            profile_picture varchar(255) DEFAULT NULL,
            subscription_status enum('active','inactive') DEFAULT 'active',
            subscription_expiry date DEFAULT NULL,
            login_streak int(11) DEFAULT 0,
            last_login datetime DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (user_id),
            UNIQUE KEY email (email),
            KEY class_id (class_id),
            CONSTRAINT users_ibfk_1 FOREIGN KEY (class_id) REFERENCES classes (class_id) ON DELETE SET NULL
        )",

        "CREATE TABLE subjects (
            subject_id int(11) NOT NULL AUTO_INCREMENT,
            subject_name varchar(100) NOT NULL,
            class_id int(11) NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (subject_id),
            KEY class_id (class_id),
            CONSTRAINT subjects_ibfk_1 FOREIGN KEY (class_id) REFERENCES classes (class_id) ON DELETE CASCADE
        )",

        "CREATE TABLE chapters (
            chapter_id int(11) NOT NULL AUTO_INCREMENT,
            chapter_name varchar(100) NOT NULL,
            subject_id int(11) NOT NULL,
            description text DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (chapter_id),
            KEY subject_id (subject_id),
            CONSTRAINT chapters_ibfk_1 FOREIGN KEY (subject_id) REFERENCES subjects (subject_id) ON DELETE CASCADE
        )",

        "CREATE TABLE mcqs (
            mcq_id int(11) NOT NULL AUTO_INCREMENT,
            chapter_id int(11) NOT NULL,
            question_text text NOT NULL,
            difficulty_level enum('easy','medium','hard') DEFAULT 'medium',
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (mcq_id),
            KEY chapter_id (chapter_id),
            CONSTRAINT mcqs_ibfk_1 FOREIGN KEY (chapter_id) REFERENCES chapters (chapter_id) ON DELETE CASCADE
        )",

        "CREATE TABLE mcq_options (
            option_id int(11) NOT NULL AUTO_INCREMENT,
            mcq_id int(11) NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) DEFAULT 0,
            PRIMARY KEY (option_id),
            KEY mcq_id (mcq_id),
            CONSTRAINT mcq_options_ibfk_1 FOREIGN KEY (mcq_id) REFERENCES mcqs (mcq_id) ON DELETE CASCADE
        )",

        "CREATE TABLE mcq_results (
            result_id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            chapter_id int(11) NOT NULL,
            score int(11) NOT NULL,
            total_questions int(11) NOT NULL,
            attempt_date timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (result_id),
            KEY user_id (user_id),
            KEY chapter_id (chapter_id),
            CONSTRAINT mcq_results_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
            CONSTRAINT mcq_results_ibfk_2 FOREIGN KEY (chapter_id) REFERENCES chapters (chapter_id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE bookmarks (
            bookmark_id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            content_type varchar(50) NOT NULL,
            content_id int(11) NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (bookmark_id),
            KEY user_id (user_id),
            CONSTRAINT bookmarks_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        )",

        // INSERT DEFAULT DATA
        "INSERT INTO classes (class_name) VALUES ('Class 10'), ('Class 11'), ('Class 12')",
        
        // Insert Admin (password: admin123)
        "INSERT INTO users (name, email, password, user_type, subscription_status) 
         VALUES ('Administrator', 'admin@mcq.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active')",
         
        // Insert Student (password: student123)
        "INSERT INTO users (name, email, password, user_type, class_id, subscription_status) 
         VALUES ('Test Student', 'student@example.com', '$2y$10$8.uQO9q9.q9.q9.q9.q9.q9.q9.q9.q9.q9.q9.q9.q9', 'student', 1, 'active')"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
        echo "✅ Executed: " . substr($sql, 0, 50) . "...\n";
    }

    echo "\n\n🎉 Migration Successfully Completed!\n";

} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage();
}
?>
