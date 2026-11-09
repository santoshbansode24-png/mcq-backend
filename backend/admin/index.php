<?php
/**
 * Admin Login Page
 * Veeru - Modern Premium Design
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/db.php';

$error = '';
$resetSuccess = isset($_GET['reset']) && $_GET['reset'] === 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both email/mobile/username and password';
    } else {
        try {
            $admin = null;
            $identifierTrimmed = trim($identifier);

            // 1. By Email
            if (filter_var($identifierTrimmed, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'admin' LIMIT 1");
                $stmt->execute([$identifierTrimmed]);
                $admin = $stmt->fetch();
            }

            // 2. By Phone Number / Mobile
            if (!$admin) {
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
                    $admin = $stmt->fetch();
                }
            }

            // 3. By Name / Email Prefix
            if (!$admin) {
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE user_type = 'admin' 
                      AND (LOWER(name) = LOWER(?) OR LOWER(email) LIKE LOWER(?))
                    LIMIT 1
                ");
                $stmt->execute([$identifierTrimmed, $identifierTrimmed . '%']);
                $admin = $stmt->fetch();
            }

            // 4. Fallback if 'admin' keyword entered and admin exists
            if (!$admin && strtolower($identifierTrimmed) === 'admin') {
                $stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'admin' ORDER BY user_id ASC LIMIT 1");
                $admin = $stmt->fetch();
            }
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Security: Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['user_id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                
                header('Location: select_board.php');
                exit();
            } else {
                // Security: Basic throttle to slow down brute force
                sleep(1);
                $error = 'Invalid email/mobile or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Veeru</title>
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
        
        /* Animated background particles */
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
        
        /* Floating orbs */
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
            animation-delay: 0s;
        }
        
        .orb2 {
            width: 350px;
            height: 350px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            bottom: -150px;
            right: -150px;
            animation-delay: 5s;
        }
        
        .orb3 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            top: 50%;
            left: 50%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            padding: 50px 45px;
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 10;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            animation: pulse 2.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.04); }
        }
        
        .logo h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        
        .logo p {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 13px;
            letter-spacing: 0.2px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
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
            padding: 14px 45px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s;
            outline: none;
            background: #f8fafc;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            background: #fef2f2;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #ef4444;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: #f0fdf4;
            color: #166534;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #22c55e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.55);
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .default-creds {
            margin-top: 24px;
            padding: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 14px;
            font-size: 12px;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        
        .default-creds strong {
            color: #1e293b;
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .cred-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 5px 0;
            font-weight: 500;
        }
        
        .cred-label {
            color: #64748b;
            min-width: 70px;
        }
        
        .cred-value {
            color: #1e293b;
            font-family: monospace;
            background: white;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .login-container {
                padding: 35px 25px;
            }
            .logo h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
    
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon"><i class="fa-solid fa-user-shield" style="color: white;"></i></div>
            <h1>Veeru Admin</h1>
            <p>Secure Portal v2.1.0</p>
        </div>

        <?php if ($resetSuccess): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check" style="font-size: 18px; color: #16a34a;"></i>
                <span>Password reset successfully! Please sign in with your new password.</span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px; flex-shrink: 0;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Admin Email, Mobile, or Username</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="email" 
                        name="email" 
                        placeholder="e.g. admin@example.com, mobile or admin" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ($_GET['prefill'] ?? '')); ?>"
                        required 
                        autofocus
                    >
                    <i class="fa-solid fa-envelope input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label for="password" style="margin-bottom: 0;">Password</label>
                    <a href="forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#764ba2'" onmouseout="this.style.color='#667eea'">
                        <i class="fa-solid fa-key" style="font-size: 11px; margin-right: 3px;"></i>Forgot Password?
                    </a>
                </div>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                    >
                    <i class="fa-solid fa-lock input-icon"></i>
                    <button type="button" class="toggle-btn" onclick="toggleVisibility('password', this)" tabindex="-1" title="Show/Hide Password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                Sign In to Portal <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>

        <div style="text-align: center; margin-top: 22px;">
            <a href="register_admin.php" style="color: #667eea; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#764ba2'" onmouseout="this.style.color='#667eea'">
                <i class="fa-solid fa-user-plus" style="margin-right: 5px;"></i>Need an Admin Account? Create Account
            </a>
        </div>
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
