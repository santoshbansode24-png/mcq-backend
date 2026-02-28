<?php
/**
 * OTP Table Setup Script
 * Run this once on Railway to create/verify the password_reset_otps table
 * URL: https://api.veeruapp.in/backend/setup_otp_table.php
 * 
 * DELETE THIS FILE after setup is complete!
 */

require_once 'config/db.php';

header('Content-Type: application/json');

$results = [];

// 1. Check if table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_otps'");
$tableExists = $stmt->fetch();

if (!$tableExists) {
    // Create the table (FK references users.user_id - the correct column)
    $sql = "
        CREATE TABLE IF NOT EXISTS password_reset_otps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_phone_otp (phone_number, otp_code),
            INDEX idx_expires (expires_at)
        )";
    
    try {
        $pdo->exec($sql);
        $results[] = "✅ Table 'password_reset_otps' created successfully!";
    } catch (PDOException $e) {
        // If FK fails, try without FK (safer fallback)
        $sqlNoFk = "
            CREATE TABLE IF NOT EXISTS password_reset_otps (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                otp_code VARCHAR(6) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                verified BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                INDEX idx_phone_otp (phone_number, otp_code),
                INDEX idx_expires (expires_at)
            )";
        $pdo->exec($sqlNoFk);
        $results[] = "✅ Table created (without FK - fallback mode). Error was: " . $e->getMessage();
    }
} else {
    $results[] = "✅ Table 'password_reset_otps' already exists.";
}

// 2. Verify columns
$stmt = $pdo->query("DESCRIBE password_reset_otps");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
$results[] = "📋 Columns: " . implode(', ', $columns);

// 3. Check users table has user_id and mobile columns  
$stmt = $pdo->query("DESCRIBE users");
$userCols = array_column($stmt->fetchAll(), 'Field');
$results[] = "👤 Users columns: " . implode(', ', $userCols);

$hasMobile = in_array('mobile', $userCols);
$hasPhone  = in_array('phone', $userCols);
$results[] = "📱 mobile column: " . ($hasMobile ? '✅ exists' : '❌ missing');
$results[] = "📱 phone column: " . ($hasPhone ? '✅ exists' : '❌ missing');

// 4. Count existing OTP records
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM password_reset_otps");
$count = $stmt->fetch();
$results[] = "📊 OTP records in DB: " . $count['cnt'];

// 5. Delete expired OTPs
$del = $pdo->exec("DELETE FROM password_reset_otps WHERE expires_at < NOW()");
$results[] = "🧹 Expired OTPs cleaned: $del records deleted";

echo json_encode([
    'status' => 'ok',
    'results' => $results
], JSON_PRETTY_PRINT);
?>
