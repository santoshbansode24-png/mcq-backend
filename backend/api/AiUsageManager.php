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
     * @return bool|string True if allowed, or error message string if blocked
     */
    public function canMakeRequest() {
        // 1. Check if user is Premium (Active Subscription)
        $stmt = $this->conn->prepare("SELECT subscription_status FROM users WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ PREMIUM LOGIC: If Active, BYPASS all limits
        if ($user && isset($user['subscription_status']) && $user['subscription_status'] === 'active') {
            return true; // UNLIMITED ACCESS
        }
        
        // 2. Free Users: Check Global Token Limit & Request Limit
        $tokenLimit = $this->getGlobalLimit();
        $requestLimit = $this->getRequestLimit();
        $today = date('Y-m-d');

        // 3. Get today's usage (Tokens and Request Count)
        $query = "SELECT tokens_used, request_count FROM ai_usage WHERE user_id = :user_id AND usage_date = :date LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $this->userId, ':date' => $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $tokensUsed = $row ? (int)$row['tokens_used'] : 0;
        $requestsMade = $row ? (int)$row['request_count'] : 0;

        // Check if ANY limit is reached
        if ($tokensUsed >= $tokenLimit || $requestsMade >= $requestLimit) {
            return "LIMIT EXHAUSTED: You have reached your daily limit. You can use this feature after 12 hours.";
        }

        return true;
    }

    /**
     * Track usage after a successful API call
     */
    public function logUsage($tokensUsed) {
        $today = date('Y-m-d');
        
        // Insert or Update (Upsert)
        $query = "INSERT INTO ai_usage (user_id, usage_date, tokens_used, request_count) 
                  VALUES (:user_id, :date, :tokens, 1) 
                  ON DUPLICATE KEY UPDATE 
                  tokens_used = tokens_used + :tokens_update, 
                  request_count = request_count + 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $this->userId,
            ':date' => $today,
            ':tokens' => $tokensUsed,
            ':tokens_update' => $tokensUsed
        ]);
    }
}
?>
