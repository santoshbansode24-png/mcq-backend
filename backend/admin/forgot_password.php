<?php
/**
 * Admin Forgot Password Page
 * Veeru - Reset Password with 4-Digit Security PIN
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = sanitizeInput($_POST['email'] ?? '');
    $security_pin = sanitizeInput($_POST['security_pin'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

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
            // Find admin user by email or mobile
            $user = null;
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND user_type = 'admin'");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();
            } else {
                $cleanedMobile = preg_replace('/[^0-9]/', '', $identifier);
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (RIGHT(mobile, 10) = RIGHT(?, 10) OR RIGHT(phone, 10) = RIGHT(?, 10)) 
                      AND user_type = 'admin'
                ");
                $stmt->execute([$cleanedMobile, $cleanedMobile]);
                $user = $stmt->fetch();
            }

            if (!$user) {
                $error = 'No admin account found matching this Email or Mobile Number.';
            } else {
                // If security_pin is empty in DB, initialize it with the entered PIN
                if (empty($user['security_pin'])) {
                    $user['security_pin'] = $security_pin;
                }

                if ($user['security_pin'] !== $security_pin) {
                    $error = 'Incorrect 4-digit Security PIN.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $update = $pdo->prepare("
                        UPDATE users 
                        SET password = ?, security_pin = ?, updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $update->execute([$hashed_password, $security_pin, $user['user_id']]);
                    $success = 'Password updated successfully! You can now log in with your new password.';
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
    <title>Reset Password - Veeru Admin</title>
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
        
        .reset-container {
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
        
        .header {
            text-align: center;
            margin-bottom: 30px;
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
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
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
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 18px 14px 48px;
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
            letter-spacing: 6px;
            font-weight: 700;
            font-size: 18px !important;
            text-align: center;
            padding-left: 18px !important;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            padding: 14px 18px;
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
            padding: 14px 18px;
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
            margin-top: 24px;
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
    
    <div class="reset-container">
        <div class="header">
            <div class="logo-icon"><i class="fa-solid fa-key" style="color: white;"></i></div>
            <h1>Reset Password</h1>
            <p>Enter your email/mobile & 4-digit Security PIN</p>
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
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Admin Email or Mobile</label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="email" 
                        name="email" 
                        placeholder="admin@example.com" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required 
                        autofocus
                    >
                    <i class="fa-solid fa-user input-icon"></i>
                </div>
            </div>

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
                <label for="new_password">New Password</label>
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
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                Reset Password <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Login Page</a>
        </div>
    </div>
</body>
</html>
