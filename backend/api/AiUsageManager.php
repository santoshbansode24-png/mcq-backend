<?php
require_once __DIR__ . '/../config/db.php';

class AiUsageManager {
    private $conn;
    private $userId;

    public function __construct($userId) {
        $db = new Database();
        $this->conn = $db->getConnection();
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
        
        // 2. Free Users: Check Global Limit
        $limit = $this->getGlobalLimit();
        $today = date('Y-m-d');

        // 3. Get today's usage
        $query = "SELECT tokens_used FROM ai_usage WHERE user_id = :user_id AND usage_date = :date LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $this->userId, ':date' => $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $used = $row ? (int)$row['tokens_used'] : 0;

        if ($used >= $limit) {
            return "You have reached your daily AI limit ($limit tokens). Please try again tomorrow or upgrade to Premium for Unlimited AI.";
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
                  tokens_used = tokens_used + :tokens, 
                  request_count = request_count + 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $this->userId,
            ':date' => $today,
            ':tokens' => $tokensUsed
        ]);
    }
}
?>
