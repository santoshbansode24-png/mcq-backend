<?php
/**
 * Quick Admin Tool: Generate School Access Code
 * Access via: http://localhost/veeru/admin_generate_school_code.php
 */
require_once 'config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = $_POST['school_name'] ?? '';
    $valid_until = $_POST['valid_until'] ?? '';
    
    if ($school_name && $valid_until) {
        // Generate random 8-character code
        $access_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO school_subscriptions (school_name, access_code, valid_until) VALUES (?, ?, ?)");
            $stmt->execute([$school_name, $access_code, $valid_until]);
            $message = "<div style='color:green'>Success! Created access code <b>$access_code</b> for $school_name. Valid until: $valid_until</div>";
        } catch (PDOException $e) {
            $message = "<div style='color:red'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div style='color:red'>Please fill all fields.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin: Generate School Code</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { margin-top: 20px; }
        input { margin-bottom: 10px; padding: 8px; width: 300px; display: block; }
        button { padding: 10px 20px; background: #0F172A; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>Admin: Generate School Access Code</h2>
    <p>Give this code to the school principal. Teachers will use it to sign up on the Play Store app.</p>
    
    <?= $message ?>
    
    <form method="POST">
        <label>School Name:</label>
        <input type="text" name="school_name" required placeholder="e.g. Delhi Public School">
        
        <label>Valid Until (Expiry Date):</label>
        <input type="date" name="valid_until" required>
        
        <button type="submit">Generate Code</button>
    </form>
</body>
</html>
