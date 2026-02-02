<?php
// Test Registration Fix
require_once 'backend/config/db.php';

// Mock Input Data
$test_email = 'test_fix_' . time() . '@example.com';
$input = [
    'name' => 'Test User Fix',
    'email' => $test_email,
    'mobile' => '9998887776',
    'password' => 'password123',
    'school_name' => 'Test High School',
    'class_id' => 10,
    'board_type' => 'CBSE'
];

echo "Simulating Registration for: " . $input['email'] . "\n";

// Use curl to call the local API
$ch = curl_init('http://localhost/veeru/backend/api/register.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

echo "API Response: " . $response . "\n\n";

// Verify Database
echo "Verifying Database Content:\n";
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$test_email]);
$user = $stmt->fetch();

if ($user) {
    echo "[PASS] User found!\n";
    echo "School Name: " . ($user['school_name'] === 'Test High School' ? "✅ " . $user['school_name'] : "❌ " . $user['school_name']) . "\n";
    echo "Mobile: " . ($user['mobile'] === '9998887776' ? "✅ " . $user['mobile'] : "❌ " . $user['mobile']) . "\n";
    echo "Phone (Legacy): " . ($user['phone'] === '9998887776' ? "✅ " . $user['phone'] : "❌ " . $user['phone']) . "\n";
    echo "Board Type: " . ($user['board_type'] === 'CBSE' ? "✅ " . $user['board_type'] : "❌ " . $user['board_type']) . "\n";
    echo "Board (Legacy): " . ($user['board'] === 'CBSE' ? "✅ " . $user['board'] : "❌ " . $user['board']) . "\n";
} else {
    echo "[FAIL] User not found in database.\n";
}
?>
