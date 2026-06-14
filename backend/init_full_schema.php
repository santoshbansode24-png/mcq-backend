<?php
// backend/init_full_schema.php
require_once 'config/db.php';

echo "<h1>Initializing FULL Cloud Database Schema</h1>";

try {
    $sql = "
    -- Users Table
    CREATE TABLE IF NOT EXISTS `users` (
      `user_id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(150) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `user_type` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
      `phone` VARCHAR(20),
      `class_id` INT DEFAULT NULL,
      `subscription_status` ENUM('active', 'inactive') DEFAULT 'inactive',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Classes Table
    CREATE TABLE IF NOT EXISTS `classes` (
      `class_id` INT AUTO_INCREMENT PRIMARY KEY,
      `class_name` VARCHAR(50) NOT NULL UNIQUE,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Subjects Table
    CREATE TABLE IF NOT EXISTS `subjects` (
      `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
      `class_id` INT NOT NULL,
      `subject_name` VARCHAR(100) NOT NULL,
      `description` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
    );

    -- Chapters Table
    CREATE TABLE IF NOT EXISTS `chapters` (
      `chapter_id` INT AUTO_INCREMENT PRIMARY KEY,
      `subject_id` INT NOT NULL,
      `chapter_name` VARCHAR(150) NOT NULL,
      `description` TEXT,
      `chapter_order` INT DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE
    );

    -- MCQs Table (The one that was missing!)
    CREATE TABLE IF NOT EXISTS `mcqs` (
      `mcq_id` INT AUTO_INCREMENT PRIMARY KEY,
      `chapter_id` INT NOT NULL,
      `question` TEXT NOT NULL,
      `option_a` TEXT NOT NULL,
      `option_b` TEXT NOT NULL,
      `option_c` TEXT NOT NULL,
      `option_d` TEXT NOT NULL,
      `correct_answer` ENUM('a', 'b', 'c', 'd') NOT NULL,
      `explanation` TEXT,
      `difficulty` ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`chapter_id`) ON DELETE CASCADE
    );
    
    -- MCQ Scores / Progress Table
    CREATE TABLE IF NOT EXISTS `mcq_scores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `chapter_id` INT NOT NULL,
        `score` INT NOT NULL,
        `total_questions` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_score (user_id),
        INDEX idx_chapter_score (chapter_id)
    );

    -- Videos Table
    CREATE TABLE IF NOT EXISTS `videos` (
      `video_id` INT AUTO_INCREMENT PRIMARY KEY,
      `chapter_id` INT NOT NULL,
      `title` VARCHAR(200) NOT NULL,
      `url` VARCHAR(500) NOT NULL,
      `description` TEXT,
      `duration` VARCHAR(20),
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Notes Table
    CREATE TABLE IF NOT EXISTS `notes` (
      `note_id` INT AUTO_INCREMENT PRIMARY KEY,
      `chapter_id` INT NOT NULL,
      `title` VARCHAR(200) NOT NULL,
      `file_path` VARCHAR(255),
      `content` LONGTEXT,
      `note_type` ENUM('pdf', 'html') DEFAULT 'html',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($sql);
    echo "✅ Tables Created Successfully (Users, Classes, Subjects, Chapters, MCQs, Videos, Notes).<br>";

    // Now populate initial data
    echo "<h2>Populating Data...</h2>";
    
    // Class 10
    $pdo->exec("INSERT IGNORE INTO classes (class_id, class_name) VALUES (10, 'Class 10')");
    
    // Subjects
    $subjects = ['Mathematics', 'Science', 'English', 'Social Studies'];
    foreach ($subjects as $sub) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (class_id, subject_name, description) VALUES (10, ?, ?)");
        $stmt->execute([$sub, "$sub for Class 10"]);
    }
    
    // Dummy Chapter for Math
    $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE subject_name = 'Mathematics' AND class_id = 10");
    $stmt->execute();
    $mathId = $stmt->fetchColumn();
    
    if ($mathId) {
        $pdo->prepare("INSERT IGNORE INTO chapters (subject_id, chapter_name, description) VALUES (?, 'Real Numbers', 'Introduction to Real Numbers')")->execute([$mathId]);
        echo "✅ Dummy Chapter 'Real Numbers' added.<br>";
    }

    echo "<h3>✅ Core Tables Ready!</h3>";

} catch (PDOException $e) {
    echo "❌ SQL Error: " . $e->getMessage();
}

// ============================================
// OTP TABLE SETUP (Added for Twilio WhatsApp)
// ============================================
echo "<h2>📱 Setting up OTP Table...</h2>";
try {
    $otpSql = "CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        phone_number VARCHAR(100) NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        INDEX idx_phone_otp (phone_number, otp_code),
        INDEX idx_expires (expires_at)
    )";
    $pdo->exec($otpSql);
    echo "✅ Table 'password_reset_otps' is ready!<br>";

    // Check columns
    $cols = $pdo->query("SHOW COLUMNS FROM password_reset_otps")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Columns: " . implode(', ', $cols) . "<br>";

    // Check users table has mobile column
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $hasMobile = in_array('mobile', $userCols);
    echo "📱 users.mobile column: " . ($hasMobile ? '✅ Exists' : '⚠️ Missing - add it manually') . "<br>";
    
    // Count OTPs
    $cnt = $pdo->query("SELECT COUNT(*) FROM password_reset_otps")->fetchColumn();
    echo "📊 Existing OTP records: $cnt<br>";

    // Clean expired
    $del = $pdo->exec("DELETE FROM password_reset_otps WHERE expires_at < NOW()");
    echo "🧹 Cleaned $del expired OTP records<br>";

    echo "<h3>✅ OTP Table is Ready!</h3>";

} catch (PDOException $e) {
    echo "❌ OTP Table Error: " . $e->getMessage() . "<br>";
    echo "💡 Try running without FK constraint...<br>";
}

// ============================================
// TWILIO CREDENTIALS CHECK
// ============================================
echo "<h2>🔑 Twilio Credentials Check...</h2>";
require_once 'config/sms_config.php';
$sid    = defined('TWILIO_ACCOUNT_SID')    ? TWILIO_ACCOUNT_SID    : '';
$token  = defined('TWILIO_AUTH_TOKEN')     ? TWILIO_AUTH_TOKEN      : '';
$number = defined('TWILIO_WHATSAPP_NUMBER')? TWILIO_WHATSAPP_NUMBER : '';

echo "Account SID: "   . (strlen($sid)    > 5 ? '✅ Set (' . substr($sid, 0, 6) . '...)' : '❌ NOT SET - Add TWILIO_ACCOUNT_SID in Railway Variables') . "<br>";
echo "Auth Token: "    . (strlen($token)  > 5 ? '✅ Set'                                 : '❌ NOT SET - Add TWILIO_AUTH_TOKEN in Railway Variables')   . "<br>";
echo "WhatsApp No: "   . (strlen($number) > 5 ? '✅ ' . $number                          : '❌ NOT SET - Add TWILIO_WHATSAPP_NUMBER in Railway Variables') . "<br>";

if (strlen($sid) < 5 || strlen($token) < 5) {
    echo "<br>⚠️ <strong>GO TO: Railway Dashboard → Your Service → Variables → Add the 3 Twilio variables</strong><br>";
} else {
    echo "<br>✅ <strong>Credentials loaded! Ready to send WhatsApp OTPs.</strong><br>";
}

echo "<br><strong>✅ ALL DONE!</strong>";
?>
