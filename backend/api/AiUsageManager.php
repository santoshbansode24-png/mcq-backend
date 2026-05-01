<?php
require_once __DIR__ . '/../config/db.php';

class AiUsageManager {
    private $conn;
    private $userId;

    public function __construct($userId) {
        global $pdo;
        
        // Final fallback if global $pdo is not yet set
        if (!$pdo) {
            require_once __DIR__ . '/../config/db.php';
        }

        if (!$pdo) {
            throw new Exception("AI Service Error: Database connection could not be established.");
        }

        $this->conn = $pdo;
        $this->userId = $userId;
    }

    /**
     * Get the Global Daily Token Limit
     */
    public function getGlobalLimit() {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'ai_global_limit_daily' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['setting_value'] : 50000; // Default fallback
    }

    /**
     * Get the Daily Request Count Limit for Free Users
     */
    public function getRequestLimit() {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'ai_free_request_limit_daily' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['setting_value'] : 5; // Default: 5 requests per day for free users
    }

    /**
     * Check if user can make a request
     * Optimized to perform a single query instead of 4 separate calls.
     * @return bool|string True if allowed, or error message string if blocked
     */
    public function canMakeRequest() {
        $today = date('Y-m-d');
        
        try {
            // Fetch everything in one single query to optimize database roundtrips
            $query = "SELECT 
                        u.subscription_status,
                        (SELECT setting_value FROM system_settings WHERE setting_key = 'ai_global_limit_daily' LIMIT 1) as global_limit,
                        (SELECT setting_value FROM system_settings WHERE setting_key = 'ai_free_request_limit_daily' LIMIT 1) as request_limit,
                        au.tokens_used,
                        au.request_count
                      FROM users u
                      LEFT JOIN ai_usage au ON u.user_id = au.user_id AND au.usage_date = :date
                      WHERE u.user_id = :user_id
                      LIMIT 1";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':user_id' => $this->userId, ':date' => $today]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) return "User account not found. Unauthorized."; // CRITICAL: Do not allow bypass for fake user IDs
            
            // 1. PREMIUM BYPASS: If Active, skip all limits
            if ($data['subscription_status'] === 'active') {
                return true;
            }
            
            // 2. Free Users: Check Limits
            $globalLimit = (int)($data['global_limit'] ?? 50000);
            $requestLimit = (int)($data['request_limit'] ?? 5);
            $tokensUsed = (int)($data['tokens_used'] ?? 0);
            $requestsMade = (int)($data['request_count'] ?? 0);
            
            if ($tokensUsed >= $globalLimit || $requestsMade >= $requestLimit) {
                return "LIMIT EXHAUSTED: You have reached your daily limit. You can use this feature after 12 hours.";
            }
            
            return true;
        } catch (Exception $e) {
            // If checking limits fails, allow the request to not block the user
            error_log("AiUsageManager canMakeRequest Error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Track usage after a successful API call
     * Fixed 'Invalid parameter number' error by using standard VALUES() and positional parameters.
     */
    public function logUsage($tokensUsed) {
        $today = date('Y-m-d');
        $tokens = (int)$tokensUsed;
        
        try {
            // Standard INSERT ... ON DUPLICATE KEY UPDATE
            // Avoid using deprecated VALUES() to prevent 'Invalid parameter number' / syntax errors.
            $query = "INSERT INTO ai_usage (user_id, usage_date, tokens_used, request_count) 
                      VALUES (?, ?, ?, 1) 
                      ON DUPLICATE KEY UPDATE 
                      tokens_used = tokens_used + ?, 
                      request_count = request_count + 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->userId, $today, $tokens, $tokens]);
        } catch (Exception $e) {
            // SILENT ERROR: Do not crash the app if usage logging fails.
            // The user already received their response; tracking failure is internal.
            error_log("AiUsageManager logUsage Database Error: " . $e->getMessage());
        }
    }
}
?>
