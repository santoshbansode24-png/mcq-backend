<?php
/**
 * Admin Forgot Password Page
 * Veeru - Reset Admin Password with Security PIN or Emergency Recovery PIN
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/db.php';

$error = '';
$success = '';
$prefillIdentifier = htmlspecialchars($_GET['prefill'] ?? ($_POST['identifier'] ?? ''));

// Helper function to log reset attempts
function logAdminResetAttempt($pdo, $user_id, $email, $mobile, $ip_address, $user_agent, $status, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_logs (user_id, email, mobile, ip_address, user_agent, status, message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $email, $mobile, $ip_address, $user_agent, $status, $message]);
    } catch (Exception $e) {
        error_log("Failed to insert password_reset_logs: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier       = sanitizeInput($_POST['identifier'] ?? '');
    $security_pin     = sanitizeInput($_POST['security_pin'] ?? '');
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (empty($identifier) || empty($security_pin) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^\d{4}$/', $security_pin)) {
        $error = 'Security PIN must be exactly 4 digits.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } else {
        try {
            // Find admin user by email, phone, name, or 'admin'
            $user = null;
            $identifierTrimmed = trim($identifier);

            // 1. By Email
            if (filter_var($identifierTrimmed, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'admin' LIMIT 1");
                $stmt->execute([$identifierTrimmed]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // 2. By Phone Number / Mobile
            if (!$user) {
                $cleanedMobile = preg_replace('/[^0-9]/', '', $identifierTrimmed);
                if (strlen($cleanedMobile) >= 10) {
                    $stmt = $pdo->prepare("
                        SELECT * FROM users 
                        WHERE user_type = 'admin' 
                          AND (
                              RIGHT(COALESCE(phone_number, ''), 10) = RIGHT(?, 10) 
                              OR RIGHT(COALESCE(mobile, ''), 10) = RIGHT(?, 10) 
                              OR RIGHT(COALESCE(phone, ''), 10) = RIGHT(?, 10)
                          )
                        LIMIT 1
                    ");
                    $stmt->execute([$cleanedMobile, $cleanedMobile, $cleanedMobile]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            // 3. By Name / Email prefix
            if (!$user) {
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE user_type = 'admin' 
                      AND (LOWER(name) = LOWER(?) OR LOWER(email) LIKE LOWER(?))
                    LIMIT 1
                ");
                $stmt->execute([$identifierTrimmed, $identifierTrimmed . '%']);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // 4. Default fallback: if keyword 'admin' entered and only one admin exists
            if (!$user && strtolower($identifierTrimmed) === 'admin') {
                $stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'admin' ORDER BY user_id ASC LIMIT 1");
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$user) {
                logAdminResetAttempt($pdo, null, $identifier, null, $ip_address, $user_agent, 'failed_user', 'No admin found matching identifier.');
                $error = 'No admin account found matching this Email, Mobile Number, or Username.';
            } else {
                // Master PIN support: 9999 or 1234 or env ADMIN_MASTER_PIN
                $masterPin = getenv('ADMIN_MASTER_PIN') ?: '9999';
                $isPinValid = false;

                // If user has no security_pin set in DB yet, adopt the entered PIN!
                if (empty($user['security_pin'])) {
                    $isPinValid = true;
                    $user['security_pin'] = $security_pin;
                } elseif ($user['security_pin'] === $security_pin || $security_pin === $masterPin || $security_pin === '1234') {
                    $isPinValid = true;
                }

                if (!$isPinValid) {
                    logAdminResetAttempt($pdo, $user['user_id'], $user['email'], $user['phone_number'] ?? null, $ip_address, $user_agent, 'failed_pin', 'Incorrect PIN.');
                    $error = 'Incorrect 4-digit Security PIN. (Tip: If you forgot your personal PIN, Emergency Master PIN 9999 is supported).';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password and security_pin
                    try {
                        $update = $pdo->prepare("
                            UPDATE users 
                            SET password = ?, security_pin = ?, updated_at = NOW() 
                            WHERE user_id = ?
                        ");
                        $update->execute([$hashed_password, $security_pin, $user['user_id']]);
                    } catch (PDOException $e) {
                        // If updated_at is missing on some schema, update without it
                        $update = $pdo->prepare("
                            UPDATE users 
                            SET password = ?, security_pin = ? 
                            WHERE user_id = ?
                        ");
                        $update->execute([$hashed_password, $security_pin, $user['user_id']]);
                    }

                    logAdminResetAttempt($pdo, $user['user_id'], $user['email'], $user['phone_number'] ?? null, $ip_address, $user_agent, 'success', 'Password reset successfully.');

                    $success = 'Your admin password has been reset successfully! You can now sign in.';
                    $prefillIdentifier = $user['email'];
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Veeru</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.6;
            animation: float 15s ease-in-out infinite;
        }
        
        .orb1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            top: -200px;
            left: -200px;
        }
        
        .orb2 {
            width: 350px;
            height: 350px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            bottom: -150px;
            right: -150px;
            animation-delay: 5s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            padding: 45px 40px;
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 10;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 14px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
        }
        
        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        
        .header p {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .label-hint {
            font-weight: 400;
            font-size: 11px;
            color: #64748b;
            margin-left: 4px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #94a3b8;
            pointer-events: none;
        }

        .toggle-btn {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
            transition: color 0.2s;
        }

        .toggle-btn:hover {
            color: #667eea;
        }
        
        .form-group input {
            width: 100%;
            padding: 13px 45px 13px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s;
            outline: none;
            background: #f8fafc;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
        }

        .pin-wrapper {
            position: relative;
        }

        .pin-input {
            letter-spacing: 12px;
            font-weight: 800;
            font-size: 20px !important;
            text-align: center;
            padding: 12px 16px !important;
        }
        
        .error-message {
            background: #fef2f2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #ef4444;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.08);
        }

        .success-message {
            background: #f0fdf4;
            color: #166534;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #22c55e;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.08);
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(102, 126, 234, 0.45);
        }

        .info-box {
            background: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 12px;
            color: #475569;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .info-box strong {
            color: #1e293b;
        }
        
        .back-link {
            text-align: center;
            margin-top: 22px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .recovery-tip {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }

        .recovery-tip a {
            color: #764ba2;
            font-weight: 600;
            text-decoration: none;
        }
        .recovery-tip a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    
    <div class="reset-container">
        <div class="header">
            <div class="logo-icon"><i class="fa-solid fa-key" style="color: white;"></i></div>
            <h1>Reset Admin Password</h1>
            <p>Recover access using your 4-digit Security PIN</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 16px; flex-shrink: 0;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 20px; color: #16a34a;"></i>
                    <strong style="font-size: 14px;"><?php echo htmlspecialchars($success); ?></strong>
                </div>
                <p style="font-size: 12px; color: #15803d; font-weight: normal; margin-bottom: 14px;">
                    Your new password is now active. You will be redirected to the login page automatically in <span id="countdown">3</span> seconds.
                </p>
                <a href="index.php?reset=success&prefill=<?php echo urlencode($prefillIdentifier); ?>" class="btn-submit" style="margin-top: 0;">
                    Sign In Now <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <script>
                let seconds = 3;
                const counter = document.getElementById('countdown');
                const interval = setInterval(() => {
                    seconds--;
                    if (counter) counter.innerText = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = "index.php?reset=success&prefill=<?php echo urlencode($prefillIdentifier); ?>";
                    }
                }, 1000);
            </script>
        <?php else: ?>
        
            <form method="POST" action="" id="resetForm">
                <div class="form-group">
                    <label for="identifier">Admin Email, Mobile, or Username</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="identifier" 
                            name="identifier" 
                            placeholder="e.g. admin@example.com, mobile or admin" 
                            value="<?php echo htmlspecialchars($prefillIdentifier ?: ($_POST['identifier'] ?? 'admin@example.com')); ?>"
                            required 
                            autofocus
                        >
                        <i class="fa-solid fa-user-shield input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_pin">
                        4-Digit Security PIN
                        <span class="label-hint">(Default emergency PIN: <strong>9999</strong>)</span>
                    </label>
                    <div class="pin-wrapper">
                        <input 
                            type="password" 
                            id="security_pin" 
                            name="security_pin" 
                            class="pin-input"
                            placeholder="••••" 
                            maxlength="4"
                            pattern="\d{4}"
                            required
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="info-box">
                    <i class="fa-solid fa-circle-info" style="color: #667eea; margin-right: 4px;"></i>
                    <strong>First-time or unset PIN?</strong> Whatever 4-digit PIN you enter above will be automatically saved as your Security PIN. Emergency Master PIN is <strong>9999</strong>.
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Admin Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            placeholder="Min. 6 characters" 
                            minlength="6"
                            required
                        >
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="toggle-btn" onclick="toggleVisibility('new_password', this)" tabindex="-1" title="Show/Hide Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Re-enter new password" 
                            minlength="6"
                            required
                        >
                        <i class="fa-solid fa-check-double input-icon"></i>
                        <button type="button" class="toggle-btn" onclick="toggleVisibility('confirm_password', this)" tabindex="-1" title="Show/Hide Password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    Reset Password <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

        <?php endif; ?>

        <div class="back-link">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Login Page</a>
        </div>

        <?php 
        $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';
        if ($isLocal): 
        ?>
        <div class="recovery-tip">
            <span>Local Developer? </span>
            <a href="reset_admin.php" title="Instantly restore default credentials (admin@example.com / admin123)">
                <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Quick 1-Click Default Reset
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
