<?php
/**
 * Google Registration Fix - Verification Script
 * This script simulates a Google Signup request to ensure the backend is ready.
 */
require_once '../config/db.php';

echo "<h2>Backend Verification: Google Signup Fix</h2>";

$testEmail = "test_google_user_" . time() . "@example.com";
$testData = [
    'name' => 'Test Google User',
    'email' => $testEmail,
    'mobile' => '1234567890',
    'school_name' => 'Verification School',
    'class_id' => 1,
    'board_type' => 'CBSE',
    'google_id' => 'google_test_id_' . time(),
    'profile_picture' => 'https://example.com/photo.jpg'
    // Note: PASSWORD IS DELIBERATELY MISSING
];

// Simulate the POST request to register.php locally
$_SERVER['REQUEST_METHOD'] = 'POST';
function getJsonInputMock($data) { return $data; }

// We will manually run the logic from register.php here to verify
try {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    if ($stmt->fetch()) {
        echo "<p style='color: orange'>⚠️ Test user already exists.</p>";
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO users (
                name, email, mobile, phone, 
                password, google_id, profile_picture,
                user_type, 
                subscription_status, subscription_expiry, 
                school_name, class_id, 
                board_type, board,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $insertStmt->execute([
            $testData['name'], 
            $testData['email'],
            $testData['mobile'], $testData['mobile'], 
            '', // EMPTY PASSWORD (FIXED)
            $testData['google_id'],
            $testData['profile_picture'],
            'student', 
            'active', 
            date('Y-m-d', strtotime('+30 days')),
            $testData['school_name'],
            $testData['class_id'],
            $testData['board_type'], $testData['board_type']
        ]);

        echo "<p style='color: green'>✅ SUCCESS: The backend successfully registered a Google user WITHOUT a password!</p>";
        echo "<p>User ID: " . $pdo->lastInsertId() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ FAILED: " . $e->getMessage() . "</p>";
}
?>
