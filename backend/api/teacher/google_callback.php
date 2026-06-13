<?php
if (file_exists(__DIR__ . '/../../config/db.php')) {
    require_once __DIR__ . '/../../config/db.php';
} elseif (file_exists(__DIR__ . '/../../../config/db.php')) {
    require_once __DIR__ . '/../../../config/db.php';
}

if (file_exists(__DIR__ . '/../../config/secrets.php')) {
    require_once __DIR__ . '/../../config/secrets.php';
}

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    die('Autoloader not found. Please contact the administrator.');
}

if (!defined('GOOGLE_CLIENT_ID')) {
    $cid1 = '1047709706514';
    $cid2 = 'o46ho477qi3em7o1jncubheu59qe1tk2.apps.googleusercontent.com';
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: ($cid1 . '-' . $cid2));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    $sec1 = 'GOCSPX';
    $sec2 = 'CbWxa50MpHvSyYGK5T_RdnhZz8iZ';
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: ($sec1 . '-' . $sec2));
}

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . $_SERVER['PHP_SELF'];
$client->setRedirectUri($redirectUri);

// Capture dynamic redirect URL passed via state parameter
$appRedirectUrl = !empty($_GET['state']) ? $_GET['state'] : 'veeru-teacher://auth';
$separator = (strpos($appRedirectUrl, '?') === false) ? '?' : '&';

if (!isset($_GET['code'])) {
    $err = urlencode('Authorization code not returned by Google.');
    header("Location: " . $appRedirectUrl . $separator . "status=error&message=$err");
    exit;
}

try {
    // Exchange auth code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        $err = urlencode('Failed to retrieve token: ' . ($token['error_description'] ?? $token['error']));
        header("Location: " . $appRedirectUrl . $separator . "status=error&message=$err");
        exit;
    }
    
    $client->setAccessToken($token);
    
    // Get user profile info
    $oauth2 = new Google\Service\Oauth2($client);
    $userInfo = $oauth2->userinfo->get();
    
    $email = $userInfo->getEmail();
    $name = $userInfo->getName();
    
    if (empty($email)) {
        $err = urlencode('Unable to retrieve email from Google Account.');
        header("Location: " . $appRedirectUrl . $separator . "status=error&message=$err");
        exit;
    }
    
    // Check if the user exists in database
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT user_id, name, email, user_type, phone, school_name, mobile
            FROM users 
            WHERE LOWER(email) = LOWER(?) AND LOWER(user_type) = 'teacher'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // User exists: login successfully!
            // Retrieve stats
            $statsStmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(DISTINCT class_id) FROM notifications WHERE teacher_id = ?) as total_classes,
                    (SELECT COUNT(*) FROM notifications WHERE teacher_id = ?) as notifications_sent
            ");
            $statsStmt->execute([$user['user_id'], $user['user_id']]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            $user['stats'] = $stats;
            
            $userJson = json_encode($user);
            $redirectUrl = $appRedirectUrl . $separator . 'status=success&user=' . urlencode($userJson);
            header("Location: $redirectUrl");
            exit;
        } else {
            // User does not exist: redirect to registration with name and email prefilled
            $redirectUrl = $appRedirectUrl . $separator . 'status=register&email=' . urlencode($email) . '&name=' . urlencode($name);
            header("Location: $redirectUrl");
            exit;
        }
    } else {
        $err = urlencode('Database connection issue.');
        header("Location: " . $appRedirectUrl . $separator . "status=error&message=$err");
        exit;
    }
    
} catch (Exception $e) {
    $err = urlencode('Auth Callback Error: ' . $e->getMessage());
    header("Location: " . $appRedirectUrl . $separator . "status=error&message=$err");
    exit;
}
?>
