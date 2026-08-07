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
        return $row ? (int)$row['setting_value'] : 50000;
    }

    /**
     * Get the Daily Request Count Limit for Free Users (Default: 6 scans/day)
     */
    public function getRequestLimit() {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'ai_free_request_limit_daily' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['setting_value'] : 6;
    }

    /**
     * Check if user can make an AI request
     * Active subscription users get UNLIMITED access.
     * Free users get up to 6 requests per day.
     * @return bool|string True if allowed, or user-friendly error message if limit reached
     */
    public function canMakeRequest() {
        $today = date('Y-m-d');
        
        try {
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
            
            if (!$data) return true; // Allow guest/temp users
            
            // 1. UNLIMITED ACCESS FOR ACTIVE SUBSCRIPTION SUBSCRIBERS
            if (isset($data['subscription_status']) && strtolower(trim($data['subscription_status'])) === 'active') {
                return true;
            }
            
            // 2. FREE USERS: 6 Daily Request Limit
            $requestLimit = (int)($data['request_limit'] ?? 6);
            if ($requestLimit < 1) $requestLimit = 6;

            $requestsMade = (int)($data['request_count'] ?? 0);
            
            if ($requestsMade >= $requestLimit) {
                return "DAILY LIMIT REACHED: Free accounts get 6 AI study scans per day. Upgrade to Premium for UNLIMITED access!";
            }
            
            return true;
        } catch (Exception $e) {
            error_log("AiUsageManager canMakeRequest Error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Track usage after a successful API call
     */
    public function logUsage($tokensUsed = 100) {
        $today = date('Y-m-d');
        try {
            $sql = "INSERT INTO ai_usage (user_id, usage_date, tokens_used, request_count) 
                    VALUES (:user_id, :usage_date, :tokens_used, 1)
                    ON DUPLICATE KEY UPDATE 
                        tokens_used = tokens_used + :tokens_used_dup,
                        request_count = request_count + 1";
                        
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':user_id' => $this->userId,
                ':usage_date' => $today,
                ':tokens_used' => $tokensUsed,
                ':tokens_used_dup' => $tokensUsed
            ]);
        } catch (Exception $e) {
            error_log("AiUsageManager logUsage Error: " . $e->getMessage());
        }
    }
}
?>
