<?php
/**
 * YouTube OAuth2 Authorization Endpoint
 * 
 * Endpoint: GET /api/teacher/youtube_auth.php
 */

require_once '../../config/db.php';
require_once '../../config/secrets.php';
require_once '../cors_middleware.php';

// Define Redirect URI
$redirect_uri = "https://api.veeruapp.in/api/teacher/youtube_auth.php";

// If locally testing/running, we can fallback to the current request's host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $redirect_uri = $protocol . $host . "/backend/api/teacher/youtube_auth.php";
}

// ACTION 1: Generate OAuth URL for the React Native/PWA App
if (isset($_GET['action']) && $_GET['action'] === 'url') {
    if (!isset($_GET['teacher_id']) || empty($_GET['teacher_id'])) {
        sendResponse('error', 'teacher_id is required', null, 400);
    }
    $teacher_id = intval($_GET['teacher_id']);
    
    // Scopes needed: youtube.readonly to detect live broadcasts
    $scopes = [
        'https://www.googleapis.com/auth/youtube.readonly'
    ];
    
    $params = [
        'response_type' => 'code',
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirect_uri,
        'scope' => implode(' ', $scopes),
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $teacher_id
    ];
    
    $auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);
    sendResponse('success', 'Auth URL generated', ['url' => $auth_url]);
}

// ACTION 2: Handle OAuth Callback from Google
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $teacher_id = isset($_GET['state']) ? intval($_GET['state']) : 0;
    
    if ($teacher_id <= 0) {
        showErrorPage("Invalid state parameter (teacher_id missing).");
    }
    
    // Exchange Auth Code for Refresh Token
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $err = json_decode($response, true);
        $err_msg = isset($err['error_description']) ? $err['error_description'] : 'Token exchange failed';
        showErrorPage("Google OAuth Error: " . $err_msg);
    }
    
    $tokens = json_decode($response, true);
    if (!isset($tokens['refresh_token'])) {
        showErrorPage("Failed to get refresh token. Please disconnect and reconnect your Google account to grant offline permission.");
    }
    
    $refresh_token = $tokens['refresh_token'];
    $access_token = $tokens['access_token'];
    
    // Optional: Fetch Channel ID using the access token to pre-store it
    $channel_id = null;
    $ch = curl_init("https://www.googleapis.com/youtube/v3/channels?part=id&mine=true");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $access_token,
        "Accept: application/json"
    ]);
    $channel_response = curl_exec($ch);
    $channel_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($channel_http_code === 200) {
        $channel_data = json_decode($channel_response, true);
        if (!empty($channel_data['items'])) {
            $channel_id = $channel_data['items'][0]['id'];
        }
    }
    
    // Save to Database
    try {
        $stmt = $pdo->prepare("UPDATE users SET youtube_refresh_token = ?, youtube_channel_id = ? WHERE user_id = ? AND user_type = 'teacher'");
        $stmt->execute([$refresh_token, $channel_id, $teacher_id]);
        
        showSuccessPage();
        
    } catch (PDOException $e) {
        showErrorPage("Database Error: " . $e->getMessage());
    }
}

// Fallback error
if (!isset($_GET['code']) && !isset($_GET['action'])) {
    sendResponse('error', 'Invalid request parameters', null, 400);
}

/**
 * Helper to display a premium styling Success HTML page
 */
function showSuccessPage() {
    header("Content-Type: text/html; charset=UTF-8");
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YouTube Connected successfully!</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
                color: #f8fafc;
                font-family: 'Outfit', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
                box-sizing: border-box;
            }
            .card {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 24px;
                padding: 40px 30px;
                text-align: center;
                max-width: 480px;
                width: 100%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                animation: fadeIn 0.6s ease-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon-wrapper {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px auto;
                box-shadow: 0 8px 16px rgba(34, 197, 94, 0.2);
            }
            .icon-wrapper svg {
                width: 40px;
                height: 40px;
                fill: white;
            }
            h1 {
                font-size: 28px;
                font-weight: 800;
                margin: 0 0 12px 0;
                background: linear-gradient(to right, #38bdf8, #818cf8);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            p {
                color: #94a3b8;
                font-size: 16px;
                line-height: 1.6;
                margin: 0 0 30px 0;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
                color: white;
                text-decoration: none;
                padding: 14px 32px;
                border-radius: 12px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
                transition: all 0.2s ease;
                cursor: pointer;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrapper">
                <svg viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
            <h1>Account Connected!</h1>
            <p>Your YouTube channel has been successfully connected to Veeru. You can now close this tab and return to the Veeru App to start your live class.</p>
            <button class="btn" onclick="window.close()">Close Window</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/**
 * Helper to display a premium styling Error HTML page
 */
function showErrorPage($message) {
    header("Content-Type: text/html; charset=UTF-8");
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connection Failed</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
                color: #f8fafc;
                font-family: 'Outfit', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
                box-sizing: border-box;
            }
            .card {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 24px;
                padding: 40px 30px;
                text-align: center;
                max-width: 480px;
                width: 100%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                animation: fadeIn 0.6s ease-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon-wrapper {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px auto;
                box-shadow: 0 8px 16px rgba(239, 68, 68, 0.2);
            }
            .icon-wrapper svg {
                width: 40px;
                height: 40px;
                fill: white;
            }
            h1 {
                font-size: 28px;
                font-weight: 800;
                margin: 0 0 12px 0;
                background: linear-gradient(to right, #fca5a5, #f87171);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            p {
                color: #94a3b8;
                font-size: 16px;
                line-height: 1.6;
                margin: 0 0 30px 0;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
                color: white;
                text-decoration: none;
                padding: 14px 32px;
                border-radius: 12px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                transition: all 0.2s ease;
                cursor: pointer;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .btn:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrapper">
                <svg viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </div>
            <h1>Connection Failed</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <button class="btn" onclick="window.close()">Close Window</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}
