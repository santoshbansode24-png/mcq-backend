<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

try {
    // Check if the column exists
    $colsStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'youtube_refresh_token'");
    $col = $colsStmt->fetch();
    
    if (!$col) {
        echo json_encode(['status' => 'error', 'message' => 'youtube_refresh_token column does not exist']);
        exit;
    }

    // Query all users with a non-empty youtube_refresh_token
    $stmt = $pdo->query("SELECT user_id, phone, username, user_type, youtube_channel_id, LENGTH(youtube_refresh_token) as token_len FROM users WHERE youtube_refresh_token IS NOT NULL AND youtube_refresh_token != ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'users_count' => count($users),
        'users' => $users
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
