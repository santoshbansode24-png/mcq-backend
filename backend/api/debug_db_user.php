<?php
/**
 * User Credential & Hash Diagnostic Tool
 * Access via: http://localhost/veeru/backend/api/debug_db_user.php
 * Or: https://api.veeruapp.in/backend/api/debug_db_user.php
 */
require_once 'cors_middleware.php';
require_once '../config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$search = $_GET['search'] ?? '';
$passwordVerify = $_GET['password'] ?? '';

if (empty($search) && $action !== 'stats') {
    echo json_encode([
        'status' => 'info',
        'message' => 'Please provide a search term (email or mobile) or request statistics.',
        'usage' => [
            'search_users' => '?search=user@example.com',
            'search_by_mobile' => '?search=7755952198',
            'verify_password' => '?search=user@example.com&password=yourpassword',
            'view_stats' => '?action=stats'
        ]
    ]);
    exit();
}

try {
    if ($action === 'stats') {
        // Retrieve database user stats
        $stmt = $pdo->query("SELECT user_type, subscription_status, COUNT(*) as count FROM users GROUP BY user_type, subscription_status");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $stats]);
        exit();
    }

    // Search for user by email or mobile
    $cleaned_search = preg_replace('/[^0-9]/', '', $search);
    $searchTerm = "%$search%";
    $mobile_search = strlen($cleaned_search) >= 10 ? substr($cleaned_search, -10) : '';

    $stmt = $pdo->prepare("
        SELECT user_id, name, email, mobile, password, user_type, subscription_status, subscription_expiry, last_login 
        FROM users 
        WHERE email LIKE ? OR mobile LIKE ? OR (LENGTH(?) >= 10 AND RIGHT(mobile, 10) = ?)
    ");
    $stmt->execute([$searchTerm, $searchTerm, $mobile_search, $mobile_search]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo json_encode([
            'status' => 'error',
            'message' => "No users found matching: '$search'"
        ]);
        exit();
    }

    $results = [];
    foreach ($users as $user) {
        $hash = $user['password'];
        $passwordMatch = null;
        
        if (!empty($passwordVerify)) {
            $passwordMatch = password_verify($passwordVerify, $hash) ? "YES (Match)" : "NO (Mismatch)";
        }
        
        // Remove hash from display for security, but display verification result if requested
        unset($user['password']);
        
        $user['diagnostic'] = [
            'password_verify_requested' => !empty($passwordVerify) ? 'YES' : 'NO',
            'password_matches_hash' => $passwordMatch ?? 'Not tested (provide &password=... parameter to test)',
            'mobile_length' => strlen($user['mobile'] ?? ''),
            'email_length' => strlen($user['email'] ?? ''),
        ];
        
        $results[] = $user;
    }

    echo json_encode([
        'status' => 'success',
        'query' => $search,
        'results_count' => count($results),
        'users' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query failed: ' . $e->getMessage()
    ]);
}
?>
