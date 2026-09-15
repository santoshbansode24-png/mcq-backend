<?php
/**
 * Admin Registration Page
 * Veeru - Create Custom Admin Credentials
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
$secret_key_default = 'VEERU2026'; // Secret key to protect live creation

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $security_pin = sanitizeInput($_POST['security_pin'] ?? '');
    $admin_key = sanitizeInput($_POST['admin_key'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($security_pin)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\d{4}$/', $security_pin)) {
        $error = 'Security PIN must be exactly 4 digits.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!empty($admin_key) && $admin_key !== $secret_key_default && $admin_key !== 'admin123') {
        $error = 'Invalid Admin Security Key. (Default: VEERU2026)';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                $insert = $pdo->prepare("
                    INSERT INTO users (name, email, password, user_type, security_pin, subscription_status, created_at) 
                    VALUES (?, ?, ?, 'admin', ?, 'active', NOW())
                ");
                $insert->execute([$name, $email, $hashed_password, $security_pin]);
                
                $success = 'Admin account created successfully! You can now log in with your custom credentials.';
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
    <title>Create Admin Account - Veeru</title>
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
            overflow: hidden;
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
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            padding: 45px 40px;
            width: 100%;
            max-width: 520px;
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
        
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 14px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .header p {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
            flex: 1;
        }
        
        .form-group label {
            display: block;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
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
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            outline: none;
            background: #f8fafc;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .pin-input {
            letter-spacing: 4px;
            font-weight: 700;
            font-size: 16px !important;
            text-align: center;
            padding-left: 16px !important;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            border-left: 4px solid #16a34a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.5);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
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
    </style>
</head>
<body>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    
    <div class="register-container">
        <div class="header">
            <div class="logo-icon"><i class="fa-solid fa-user-plus" style="color: white;"></i></div>
            <h1>Create Admin Account</h1>
            <p>Set up your custom login credentials</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <span style="font-size: 18px;">⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <span style="font-size: 18px;">✅</span>
                <div>
                    <div><?php echo htmlspecialchars($success); ?></div>
                    <div style="margin-top: 6px;"><a href="index.php" style="color: #166534; font-weight: 700; text-decoration: underline;">Click here to Log In</a></div>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        placeholder="Enter your full name" 
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                        required 
                        autofocus
                    >
                    <i class="fa-solid fa-id-card input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Admin Email / Username</label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your email address" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required 
                    >
                    <i class="fa-solid fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Min 6 chars" 
                            minlength="6"
                            required
                        >
                        <i class="fa-solid fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Repeat password" 
                            minlength="6"
                            required
                        >
                        <i class="fa-solid fa-check-double input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="security_pin">4-Digit Security PIN</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="security_pin" 
                            name="security_pin" 
                            class="pin-input"
                            placeholder="••••" 
                            maxlength="4"
                            pattern="\d{4}"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="admin_key">Setup Key (Optional)</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="admin_key" 
                            name="admin_key" 
                            placeholder="VEERU2026"
                        >
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                Create Admin Account <i class="fa-solid fa-user-check" style="margin-left: 8px;"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Already have an account? Log In</a>
        </div>
    </div>
</body>
</html>
