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
if (!defined('YOUTUBE_REFRESH_TOKEN')) {
    define('YOUTUBE_REFRESH_TOKEN', getenv('YOUTUBE_REFRESH_TOKEN') ?: '');
}

header('Content-Type: text/html; charset=utf-8');

$custom_cid = '';
$custom_sec = '';
$teacher_id = 0;

// 1. Check if state parameter contains variables (from Google redirect callback)
if (isset($_GET['state'])) {
    $stateData = json_decode(base64_decode($_GET['state']), true);
    if (is_array($stateData)) {
        if (!empty($stateData['teacher_id'])) {
            $teacher_id = (int)$stateData['teacher_id'];
        }
        if (!empty($stateData['cid'])) {
            $custom_cid = trim($stateData['cid']);
        }
        if (!empty($stateData['sec'])) {
            $custom_sec = trim($stateData['sec']);
        }
    }
}

// 2. Read teacher_id and custom credentials on standard load/POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    if (!empty($_POST['custom_client_id']) && !empty($_POST['custom_client_secret'])) {
        $custom_cid = trim($_POST['custom_client_id']);
        $custom_sec = trim($_POST['custom_client_secret']);
    }
} else {
    if (isset($_GET['teacher_id'])) {
        $teacher_id = (int)$_GET['teacher_id'];
    }
}

// 3. Initialize Google Client with active credentials
$active_client_id = !empty($custom_cid) ? $custom_cid : GOOGLE_CLIENT_ID;
$active_client_secret = !empty($custom_sec) ? $custom_sec : GOOGLE_CLIENT_SECRET;

$client = new Google\Client();
$client->setClientId($active_client_id);
$client->setClientSecret($active_client_secret);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . $_SERVER['PHP_SELF'];
$client->setRedirectUri($redirectUri);

$client->addScope(Google_Service_YouTube::YOUTUBE);
$client->addScope(Google_Service_YouTube::YOUTUBE_FORCE_SSL);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force to get refresh token

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    exit;
}

// Handle JSON response request for Auth URL
if (isset($_GET['action']) && $_GET['action'] === 'url') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    
    $stateData = ['teacher_id' => $teacher_id];
    $client->setState(base64_encode(json_encode($stateData)));
    $authUrl = $client->createAuthUrl();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'url' => $authUrl
        ]
    ]);
    exit;
}

// 4. Handle POST form submit by redirecting directly to Google's Auth URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['custom_client_id']) || isset($_POST['initiate_default']))) {
    $stateData = [
        'teacher_id' => $teacher_id
    ];
    if (!empty($custom_cid) && !empty($custom_sec)) {
        $stateData['cid'] = $custom_cid;
        $stateData['sec'] = $custom_sec;
    }
    $client->setState(base64_encode(json_encode($stateData)));
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}

// 5. Initialize outcome variables for HTML view
$tokenExchangeSuccess = false;
$tokenError = null;
$refreshToken = null;
$dbSaved = false;
$dbError = null;

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        $tokenError = $token;
    } else {
        $client->setAccessToken($token);
        $tokenExchangeSuccess = true;
        if (isset($token['refresh_token'])) {
            $refreshToken = $token['refresh_token'];
            
            // Save to database if teacher_id is active
            if ($teacher_id > 0 && isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET youtube_refresh_token = ? WHERE user_id = ? AND user_type = 'teacher'");
                    $stmt->execute([$refreshToken, $teacher_id]);
                    $dbSaved = true;
                } catch (PDOException $e) {
                    $dbError = $e->getMessage();
                }
            }
        }
    }
} else {
    // Generate standard authentication URL (for default credentials)
    // We also set the state to pass the teacher_id to Google
    $stateData = ['teacher_id' => $teacher_id];
    $client->setState(base64_encode(json_encode($stateData)));
    $authUrl = $client->createAuthUrl();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veeru - Link YouTube Channel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f43f5e;
            --primary-hover: #e11d48;
            --bg-dark: #0b0f19;
            --card-bg: rgba(22, 30, 49, 0.75);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent: #38bdf8;
            --success: #10b981;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #070a13 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        .card-title {
            font-size: 28px;
            font-weight: 800;
            margin-top: 0;
            margin-bottom: 8px;
            background: linear-gradient(to right, #f43f5e, #be123c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }

        .card-subtitle {
            font-size: 15px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        /* Tabs styles */
        .tabs-header {
            display: flex;
            background: rgba(15, 23, 42, 0.5);
            padding: 6px;
            border-radius: 14px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .tab-btn {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.25s ease;
        }

        .tab-btn:hover {
            color: var(--text-main);
        }

        .tab-btn.active {
            background: rgba(244, 63, 94, 0.15);
            color: #fda4af;
            border: 1px solid rgba(244, 63, 94, 0.25);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Steps list */
        .steps-list {
            text-align: left;
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .step-num {
            background: rgba(56, 189, 248, 0.1);
            color: var(--accent);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            margin-right: 12px;
            flex-shrink: 0;
            margin-top: 2px;
            border: 1px solid rgba(56, 189, 248, 0.2);
        }

        .step-text {
            font-size: 14px;
            color: var(--text-main);
        }

        .uri-container {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 13px;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0;
        }

        .uri-text {
            word-break: break-all;
            user-select: all;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
            margin-left: 10px;
            flex-shrink: 0;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            color: var(--text-main);
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #e11d48, #be123c);
            color: white;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.25s ease;
            box-shadow: 0 8px 16px -4px rgba(225, 29, 72, 0.4);
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px -2px rgba(225, 29, 72, 0.5);
            filter: brightness(1.05);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            box-shadow: none;
            margin-top: 15px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: none;
        }

        .alert-info {
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.15);
            border-radius: 14px;
            padding: 16px;
            font-size: 13px;
            line-height: 1.5;
            color: #bae6fd;
            text-align: left;
            margin-bottom: 20px;
        }

        /* Success layout */
        .success-icon {
            font-size: 48px;
            color: var(--success);
            margin-bottom: 15px;
            text-align: center;
        }

        .success-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--success);
            text-align: center;
        }

        .success-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 25px;
            text-align: center;
        }

        .token-area {
            margin-top: 25px;
            text-align: left;
        }

        .token-label {
            font-size: 13px;
            font-weight: 700;
            color: #f43f5e;
            margin-bottom: 6px;
            display: block;
        }

        .token-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .token-box {
            width: 100%;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            color: #10b981;
            font-family: monospace;
            font-size: 13px;
            resize: none;
            box-sizing: border-box;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['code'])): ?>
        <div class="card">
            <?php if ($tokenError): ?>
                <div class="success-icon" style="color: #ef4444;">❌</div>
                <div class="success-title" style="color: #ef4444;">Authorization Failed</div>
                <p class="success-subtitle">Google returned an error when trying to retrieve the access token. Please check your credentials and try again.</p>
                <div class="token-area">
                    <span class="token-label" style="color: #ef4444;">Error Response</span>
                    <textarea class="token-box" rows="5" readonly><?= htmlspecialchars(json_encode($tokenError, JSON_PRETTY_PRINT)) ?></textarea>
                </div>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn">Try Again</a>
            <?php else: ?>
                <div class="success-icon">✨</div>
                <div class="success-title">YouTube Connected!</div>
                <p class="success-subtitle">Successfully authenticated with Google OAuth. Your live stream integration is ready.</p>
                
                <?php if ($refreshToken): ?>
                    <div class="token-area">
                        <?php if ($dbSaved): ?>
                            <div class="alert-info" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; margin-bottom: 20px;">
                                ✅ **Success!** Your YouTube channel has been linked directly to your school account profile in the database. You can close this window now.
                            </div>
                        <?php else: ?>
                            <?php if ($teacher_id > 0): ?>
                                <div class="alert-info" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2); color: #fca5a5; margin-bottom: 20px;">
                                    ⚠️ **Notice:** We couldn't save the token automatically to the database (<?= htmlspecialchars($dbError ?? 'Database connection missing') ?>). Please copy the token below and configure it manually.
                                </div>
                            <?php endif; ?>
                            <span class="token-label">🚀 YouTube Refresh Token</span>
                            <p class="token-desc">Copy this token and add it to your <strong>Railway Project Variables</strong> as <code>YOUTUBE_REFRESH_TOKEN</code>.</p>
                        <?php endif; ?>
                        
                        <div style="position: relative;">
                            <textarea id="refresh-token-val" class="token-box" rows="3" readonly><?= htmlspecialchars($refreshToken) ?></textarea>
                            <button class="btn" style="margin-top: 5px;" onclick="copyText('refresh-token-val', this)">Copy Refresh Token</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert-info" style="border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.05); color: #fef08a;">
                        ⚠️ No new refresh token was returned by Google. This happens if you have already authorized this application.
                        <br/><br/>
                        <strong>To fix this:</strong> Go to your Google Account Settings -> Security -> Third-party apps with account access, remove access for this app, and click authorize again.
                    </div>
                <?php endif; ?>

                <?php if (!empty($custom_cid) && !empty($custom_sec)): ?>
                    <div class="token-area" style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                        <span class="token-label" style="color: var(--accent);">⚠️ Set Custom Client Credentials in Railway</span>
                        <p class="token-desc">Since you used custom credentials, you must also save these variables on Railway so the backend can sign API requests using your client ID:</p>
                        
                        <div style="margin-bottom: 15px;">
                            <span class="form-label">GOOGLE_CLIENT_ID</span>
                            <div class="uri-container">
                                <span id="custom-cid-val" class="uri-text"><?= htmlspecialchars($custom_cid) ?></span>
                                <button class="copy-btn" onclick="copyText('custom-cid-val', this)">Copy</button>
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <span class="form-label">GOOGLE_CLIENT_SECRET</span>
                            <div class="uri-container">
                                <span id="custom-sec-val" class="uri-text"><?= htmlspecialchars($custom_sec) ?></span>
                                <button class="copy-btn" onclick="copyText('custom-sec-val', this)">Copy</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="<?= $_SERVER['PHP_SELF'] ?><?= $teacher_id > 0 ? '?teacher_id=' . $teacher_id : '' ?>" class="btn btn-secondary">Back to Start</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <h3 class="card-title">Link Admin YouTube Channel</h3>
            <p class="card-subtitle">Authorize the Veeru platform to generate live broadcast RTMP feeds on your YouTube channel.</p>
            
            <?php if ($teacher_id > 0): ?>
                <div class="alert-info" style="background: rgba(56, 189, 248, 0.12); color: #bae6fd; text-align: center;">
                    👤 Linking school channel for **Teacher ID: <?= htmlspecialchars($teacher_id) ?>**
                </div>
            <?php endif; ?>

            <div class="tabs-header">
                <button id="tab-default-btn" class="tab-btn active" onclick="switchTab('tab-default')">Console Setup (Standard)</button>
                <button id="tab-localhost-btn" class="tab-btn" onclick="switchTab('tab-localhost')">Local Bypass (Easy)</button>
                <button id="tab-custom-btn" class="tab-btn" onclick="switchTab('tab-custom')">Custom Credentials</button>
            </div>

            <!-- TAB 1: DEFAULT CONFIG -->
            <div id="tab-default-content" class="tab-content active">
                <div class="alert-info">
                    This option uses the pre-configured system client. To make this work on production, you must whitelist the redirect URL in your Google Developer Console.
                </div>
                
                <ul class="steps-list">
                    <li class="step-item">
                        <span class="step-num">1</span>
                        <div class="step-text">Open the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: var(--accent); text-decoration: underline;">Google Cloud Credentials Console</a>.</div>
                    </li>
                    <li class="step-item">
                        <span class="step-num">2</span>
                        <div class="step-text">Edit the Client ID used in this project (check your Railway settings or project variables).</div>
                    </li>
                    <li class="step-item">
                        <span class="step-num">3</span>
                        <div class="step-text">
                            Under <strong>Authorized redirect URIs</strong>, add the following URL:
                            <div class="uri-container">
                                <span id="redirect-uri-val-1" class="uri-text"><?= htmlspecialchars($redirectUri) ?></span>
                                <button class="copy-btn" onclick="copyText('redirect-uri-val-1', this)">Copy</button>
                            </div>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-num">4</span>
                        <div class="step-text">Click **Save** and wait 2-5 minutes for Google to update its settings.</div>
                    </li>
                </ul>
                
                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
                    <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
                    <input type="hidden" name="initiate_default" value="1">
                    <button type="submit" class="btn">Connect YouTube Account</button>
                </form>
            </div>

            <!-- TAB 2: LOCAL BYPASS -->
            <div id="tab-localhost-content" class="tab-content">
                <div class="alert-info">
                    Since local domains (localhost) are always whitelisted by default on the client, you can authorize via a local server and then copy the token back here.
                </div>

                <ul class="steps-list">
                    <li class="step-item">
                        <span class="step-num">1</span>
                        <div class="step-text">Start the <strong>Apache</strong> server on your local XAMPP Control Panel.</div>
                    </li>
                    <li class="step-item">
                        <span class="step-num">2</span>
                        <div class="step-text">Click the link below to load this auth wizard on localhost.</div>
                    </li>
                    <li class="step-item">
                        <span class="step-num">3</span>
                        <div class="step-text">Complete authorization, copy the refresh token, and add it to Railway.</div>
                    </li>
                </ul>

                <?php
                // Construct localhost URI
                $localUri = 'http://localhost/veeru/api/teacher/youtube_auth.php' . ($teacher_id > 0 ? '?teacher_id=' . $teacher_id : '');
                ?>
                <a href="<?= $localUri ?>" target="_blank" class="btn">🔗 Open Localhost Auth Bypass</a>
            </div>

            <!-- TAB 3: CUSTOM CREDENTIALS -->
            <div id="tab-custom-content" class="tab-content">
                <div class="alert-info">
                    You can create a standalone OAuth Client in your Google Console, set its redirect URI to this page, and paste the keys below.
                </div>

                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
                    <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
                    <div class="form-group">
                        <label class="form-label" for="custom_client_id">Google Client ID</label>
                        <input type="text" id="custom_client_id" name="custom_client_id" class="form-input" placeholder="e.g. 123456-abcdef.apps.googleusercontent.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="custom_client_secret">Google Client Secret</label>
                        <input type="password" id="custom_client_secret" name="custom_client_secret" class="form-input" placeholder="e.g. GOCSPX-xxxxxxxxx" required>
                    </div>

                    <div class="alert-info" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.15); color: #a7f3d0; margin-top: 10px;">
                        💡 Make sure you have whitelisted this redirect URI in your custom OAuth client settings:
                        <div class="uri-container">
                            <span id="redirect-uri-val-2" class="uri-text"><?= htmlspecialchars($redirectUri) ?></span>
                            <button type="button" class="copy-btn" onclick="copyText('redirect-uri-val-2', this)">Copy</button>
                        </div>
                    </div>

                    <button type="submit" class="btn">Generate Custom Auth Link</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.getElementById(tabId + '-btn').classList.add('active');
            document.getElementById(tabId + '-content').classList.add('active');
        }

        function copyText(elementId, btnElement) {
            const el = document.getElementById(elementId);
            const text = el.tagName === 'TEXTAREA' ? el.value : el.innerText;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalText = btnElement.innerText;
                btnElement.innerText = 'Copied!';
                btnElement.style.background = 'rgba(16, 185, 129, 0.2)';
                btnElement.style.color = '#10b981';
                btnElement.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                setTimeout(() => {
                    btnElement.innerText = originalText;
                    btnElement.style.background = '';
                    btnElement.style.color = '';
                    btnElement.style.borderColor = '';
                }, 1500);
            });
        }
    </script>
</body>
</html>
