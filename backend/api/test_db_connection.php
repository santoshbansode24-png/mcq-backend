<?php
/**
 * Database Connection Diagnostic Tool
 * Tests database connection and queries test user
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [],
    'database_connection' => [],
    'test_query' => []
];

// 1. Check Environment Variables
$diagnostics['environment'] = [
    'DB_HOST' => getenv('DB_HOST') ?: 'not set',
    'DB_NAME' => getenv('DB_NAME') ?: 'not set',
    'DB_USER' => getenv('DB_USER') ?: 'not set',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***SET***' : 'not set',
    'DB_PORT' => getenv('DB_PORT') ?: 'not set'
];

// 2. Test Database Connection
try {
    if (isset($pdo)) {
        $diagnostics['database_connection']['status'] = 'SUCCESS';
        $diagnostics['database_connection']['message'] = 'PDO connection established';
        
        // 3. Test Query - Get user count
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users WHERE user_type = 'student'");
            $result = $stmt->fetch();
            $diagnostics['test_query']['user_count'] = $result['user_count'];
            $diagnostics['test_query']['status'] = 'SUCCESS';
            
            // 4. Get a sample user (without password)
            $stmt = $pdo->query("SELECT user_id, name, email, user_type, class_id, subscription_status FROM users WHERE user_type = 'student' LIMIT 1");
            $sampleUser = $stmt->fetch();
            $diagnostics['test_query']['sample_user'] = $sampleUser ?: 'No users found';
            
        } catch (PDOException $e) {
            $diagnostics['test_query']['status'] = 'ERROR';
            $diagnostics['test_query']['error'] = $e->getMessage();
        }
        
    } else {
        $diagnostics['database_connection']['status'] = 'ERROR';
        $diagnostics['database_connection']['message'] = 'PDO object not available';
    }
} catch (Exception $e) {
    $diagnostics['database_connection']['status'] = 'ERROR';
    $diagnostics['database_connection']['error'] = $e->getMessage();
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
