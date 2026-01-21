<?php
/**
 * Setup Scholarship Data on Railway Database
 * Run this file via: http://localhost/veeru/setup_railway_scholarship.php
 */

// Railway Database Connection (UPDATE THESE FROM RAILWAY'S CONNECT DIALOG)
$railway_host = 'yamanote.proxy.rlwy.net';
$railway_port = '24540';
$railway_user = 'root';
$railway_pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$railway_db = 'railway';

try {
    // Connect to Railway MySQL
    $pdo = new PDO(
        "mysql:host=$railway_host;port=$railway_port;dbname=$railway_db;charset=utf8mb4",
        $railway_user,
        $railway_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<h2>Connected to Railway Database ✅</h2>";
    
    // Create 3 Scholarship Classes
    $pdo->exec("
        INSERT INTO classes (class_id, class_name, board_type) VALUES 
        (38, 'Scholarship - Primary Level (1-4)', 'Scholarship'),
        (39, 'Scholarship - Upper Primary Level (5-7)', 'Scholarship'),
        (40, 'Scholarship - Secondary Level (8-10)', 'Scholarship')
        ON DUPLICATE KEY UPDATE class_name = VALUES(class_name)
    ");
    echo "<p>✅ Created 3 Scholarship classes</p>";
    
    // Subjects for Primary (38)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 38), ('Mathematics', 38), ('Mental Ability', 38), ('General Knowledge', 38), ('Mock Tests', 38)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    echo "<p>✅ Added subjects for Primary Level</p>";
    
    // Subjects for Upper Primary (39)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 39), ('Mathematics', 39), ('Science', 39), ('Mental Ability', 39), ('General Knowledge', 39), ('Mock Tests', 39)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    echo "<p>✅ Added subjects for Upper Primary Level</p>";
    
    // Subjects for Secondary (40)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 40), ('Mathematics', 40), ('Science', 40), ('Mental Ability', 40), ('General Knowledge', 40), ('Social Science', 40), ('Mock Tests', 40)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    echo "<p>✅ Added subjects for Secondary Level</p>";
    
    // Verify
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE board_type = 'Scholarship'");
    $classCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM subjects WHERE class_id IN (38, 39, 40)");
    $subjectCount = $stmt->fetchColumn();
    
    echo "<h3 style='color: green;'>🎉 SUCCESS!</h3>";
    echo "<p><strong>Classes Created:</strong> $classCount</p>";
    echo "<p><strong>Subjects Created:</strong> $subjectCount</p>";
    echo "<p>Your Railway database is now ready! Test the student app.</p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Make sure you updated the password in this file!</p>";
}
?>
