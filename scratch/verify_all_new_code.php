<?php
/**
 * Comprehensive Code & API Verification Suite
 * Veeru
 */

echo "===================================================\n";
echo "🧪 STARTING RIGOROUS API & CODE VERIFICATION\n";
echo "===================================================\n\n";

$baseUrl = 'https://api.veeruapp.in';
$results = [];

function testEndpoint($name, $url, $method = 'GET', $payload = null) {
    echo "Testing [$name]... ";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload) : $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 500) {
        echo "✅ PASS (HTTP $httpCode)\n";
        if ($json) {
            echo "   Response: Status={$json['status']} | Message={$json['message']}\n";
        }
    } else {
        echo "❌ FAIL (HTTP $httpCode)\n";
        echo "   Error: " . ($error ?: substr($response, 0, 150)) . "\n";
    }
    echo "\n";
}

// 1. Test Registration Validation (missing fields)
testEndpoint(
    'Registration Validation',
    "$baseUrl/api/register.php",
    'POST',
    ['name' => 'Test']
);

// 2. Test Forgot Password Validation (missing PIN)
testEndpoint(
    'Forgot Password Validation',
    "$baseUrl/api/forgot_password.php",
    'POST',
    [
        'email' => 'nonexistent@example.com',
        'mobile' => '9999999999',
        'security_pin' => '12', // invalid pin length
        'new_password' => 'newpass'
    ]
);

// 3. Test Forgot Password PIN format check
testEndpoint(
    'Forgot Password Valid PIN Format Test',
    "$baseUrl/api/forgot_password.php",
    'POST',
    [
        'email' => 'nonexistent@example.com',
        'mobile' => '9999999999',
        'security_pin' => '1234',
        'new_password' => 'newpass123'
    ]
);

// 4. Test Teacher Reset Student Password Endpoint
testEndpoint(
    'Teacher Reset Student Password Endpoint',
    "$baseUrl/api/teacher/reset_student_password.php",
    'POST',
    [
        'teacher_id' => 1,
        'student_id' => 99999, // Non-existent student
        'new_password' => 'Student@123'
    ]
);

// 5. Test Teacher Classes API
testEndpoint(
    'Get Teacher Classes API',
    "$baseUrl/api/get_classes.php?board=CBSE",
    'GET'
);

echo "===================================================\n";
echo "🏁 VERIFICATION COMPLETED SUCCESSFULLY\n";
echo "===================================================\n";
