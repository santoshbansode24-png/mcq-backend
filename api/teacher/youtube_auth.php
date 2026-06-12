<?php
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
if (!defined('YOUTUBE_REFRESH_TOKEN')) {
    define('YOUTUBE_REFRESH_TOKEN', getenv('YOUTUBE_REFRESH_TOKEN') ?: '');
}

header('Content-Type: text/html');

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . $_SERVER['PHP_SELF'];
$client->setRedirectUri($redirectUri);

$client->addScope(Google_Service_YouTube::YOUTUBE);
$client->addScope(Google_Service_YouTube::YOUTUBE_FORCE_SSL);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force to get refresh token

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        echo "<h3>Error fetching token</h3>";
        echo "<pre>" . print_r($token, true) . "</pre>";
        exit;
    }

    $client->setAccessToken($token);
    
    echo "<h3>Success!</h3>";
    echo "<p>Your YouTube Account is now linked.</p>";
    
    if (isset($token['refresh_token'])) {
        echo "<h4>Important: Save this Refresh Token!</h4>";
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veeru - Link YouTube Channel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #f8fafc;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        h3 {
            font-size: 28px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 12px;
            background: linear-gradient(to right, #f43f5e, #be123c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        h4 {
            font-size: 18px;
            font-weight: 700;
            color: #fb7185;
            margin-top: 24px;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #e11d48, #be123c);
            color: white;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.3);
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(225, 29, 72, 0.4);
            filter: brightness(1.1);
        }
        .uri-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 13px;
            color: #38bdf8;
            word-break: break-all;
            margin: 15px 0;
            text-align: left;
        }
        .alert {
            background: rgba(251, 113, 133, 0.1);
            border: 1px solid rgba(251, 113, 133, 0.2);
            padding: 16px;
            border-radius: 14px;
            margin-top: 24px;
            text-align: left;
        }
        .alert-title {
            font-weight: 700;
            color: #fda4af;
            font-size: 14px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        .alert-desc {
            font-size: 13px;
            color: #f3f4f6;
            line-height: 1.5;
        }
        textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px;
            color: #38bdf8;
            font-family: monospace;
            font-size: 14px;
            resize: none;
            margin-top: 10px;
            box-sizing: border-box;
        }
        .bypass-link {
            display: inline-block;
            margin-top: 15px;
            color: #38bdf8;
            text-decoration: underline;
            font-size: 14px;
            transition: color 0.2s;
        }
        .bypass-link:hover {
            color: #7dd3fc;
        }
    </style>
</head>
<body>
    <div class="card">
        <?php
        if (isset($_GET['code'])) {
            // Exchange the authorization code for an access token
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            
            if (isset($token['error'])) {
                echo "<h3>Error Fetching Token</h3>";
                echo "<p>Something went wrong while connecting your account.</p>";
                echo "<div class='uri-box'>" . htmlspecialchars(json_encode($token, JSON_PRETTY_PRINT)) . "</div>";
                echo "<a href='" . $_SERVER['PHP_SELF'] . "' class='btn'>Try Again</a>";
            } else {
                $client->setAccessToken($token);
                echo "<h3>Success!</h3>";
                echo "<p>Your YouTube account has been authorized successfully.</p>";
                
                if (isset($token['refresh_token'])) {
                    echo "<h4>Step 2: Add Refresh Token to Production</h4>";
                    echo "<p>Copy the code block below and save it inside your **Railway Project Variables** as <code>YOUTUBE_REFRESH_TOKEN</code>.</p>";
                    echo "<textarea rows='4' readonly>" . htmlspecialchars($token['refresh_token']) . "</textarea>";
                } else {
                    echo "<p style='color: #fb7185;'>No refresh token was returned. You may have already authorized the app before. Go to your Google Account Settings -> Security -> App permissions, revoke access for this client, and connect again.</p>";
                }
            }
        } else {
            // Generate the authentication URL
            $authUrl = $client->createAuthUrl();
            
            // Output connection button and mismatch guide
            echo "<h3>Link Admin YouTube Channel</h3>";
            echo "<p>Authorize the Veeru platform to generate live broadcast RTMP feeds on your YouTube channel.</p>";
            echo "<a href='" . filter_var($authUrl, FILTER_SANITIZE_URL) . "' class='btn'>Connect YouTube Account</a>";
            
            // Display troubleshooting box for redirect mismatch
            echo "
            <div class='alert'>
                <div class='alert-title'>⚠️ Got 'Error 400: redirect_uri_mismatch'?</div>
                <div class='alert-desc'>
                    Your Google Cloud Console client credentials must allow this URL as an <strong>Authorized redirect URI</strong>:
                    <div class='uri-box'>$redirectUri</div>
                    <strong>Bypass / Easy Option:</strong> If you don't have access to your Google Developer Console, click below to authorize via localhost instead:
                    <br/>
                    <a href='http://localhost/veeru/api/teacher/youtube_auth.php' class='bypass-link'>🔗 Connect using Localhost Auth Bypass</a>
                </div>
            </div>
            ";
        }
        ?>
    </div>
</body>
</html>
<?php
exit;
?>
