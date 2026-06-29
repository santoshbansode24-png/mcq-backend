<?php
/**
 * Mock script to test the updated teacher_login.php logic without a web server
 */

function runLoginTest($email, $password) {
    global $pdo;
    
    // Setup inputs
    $input = [
        'email' => $email,
        'password' => $password
    ];

    echo "Testing login with Email/Mobile: '$email' | Password: '$password'\n";
    
    // We will simulate the login logic by extracting the exact PHP code from teacher_login.php
    $email = trim($email);
    
    try {
        // Clean input to check if it's a mobile number
        $cleaned_digits = preg_replace('/[^0-9]/', '', $email);
        $is_mobile = false;
        $search_value = $email;

        if (strpos($email, '@') === false && is_numeric($cleaned_digits) && strlen($cleaned_digits) >= 10) {
            $is_mobile = true;
            $search_value = substr($cleaned_digits, -10);
            $field_query = "(RIGHT(mobile, 10) = ? OR RIGHT(phone, 10) = ?)";
        } else {
            $field_query = "LOWER(email) = LOWER(?)";
        }

        // Query user by email or mobile first to provide better feedback
        $stmt = $pdo->prepare("SELECT user_id as id, user_id, name, email, password, school_name, mobile, user_type FROM users WHERE $field_query");
        if ($is_mobile) {
            $stmt->execute([$search_value, $search_value]);
        } else {
            $stmt->execute([$search_value]);
        }
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            echo "RESPONSE: status=error, message='Invalid email/mobile or password' (401)\n\n";
            return;
        }

        // Verify user type
        if (strtolower($teacher['user_type']) !== 'teacher') {
            echo "RESPONSE: status=error, message='This account is registered as a {$teacher['user_type']}. Please use a teacher account.' (401)\n\n";
            return;
        }

        if (!password_verify($password, $teacher['password'])) {
            echo "RESPONSE: status=error, message='Invalid email/mobile or password' (401)\n\n";
            return;
        }

        unset($teacher['password']);
        echo "RESPONSE: status=success, message='Login successful', data=" . json_encode($teacher) . " (200)\n\n";

    } catch (PDOException $e) {
        echo "RESPONSE: status=error, message='Database error: " . $e->getMessage() . "' (500)\n\n";
    }
}

// Connect to Railway production database for real data test
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db = 'railway';
$port = 24540;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to Database.\n\n";
    
    // Test 1: Valid teacher email + valid password
    runLoginTest('santoshbansode24@gmail.com', 'veeru123');
    
    // Test 2: Valid teacher mobile + valid password
    runLoginTest('7755952198', 'veeru123');

    // Test 3: Valid teacher mobile with spaces/formatting + valid password
    runLoginTest('+91 7755952198 ', 'veeru123');
    
    // Test 4: Student email (should fail with specific account type warning)
    runLoginTest('test@veeru.com', 'veeru123');
    
    // Test 5: Invalid email
    runLoginTest('nonexistent@example.com', 'somepass');
    
    // Test 6: Valid teacher but wrong password
    runLoginTest('santoshbansode24@gmail.com', 'wrongpassword');

} catch (Exception $e) {
    echo "DB Connection Error: " . $e->getMessage() . "\n";
}
?>
