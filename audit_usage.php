<?php
require_once __DIR__ . '/backend/config/db.php';

global $pdo;

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

try {
    $query = "SELECT usage_date, SUM(tokens_used) as total_tokens, SUM(request_count) as total_requests 
              FROM ai_usage 
              WHERE usage_date IN (?, ?) 
              GROUP BY usage_date 
              ORDER BY usage_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $yesterday]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "AI USAGE REPORT:\n";
    echo "-----------------\n";
    
    foreach ($results as $row) {
        $date = $row['usage_date'];
        $tokens = $row['total_tokens'];
        $reqs = $row['total_requests'];
        
        // Gemini Flash 1.5/2.5 Price: ~$0.07 per 1M tokens
        // For approx INR: 0.07 * 83 = ₹5.8 per 1M tokens
        $cost = ($tokens / 1000000) * 5.8;
        
        echo "Date: $date\n";
        echo "Total Requests: $reqs\n";
        echo "Total Tokens: $tokens\n";
        echo "Estimated Cost (INR): ₹" . number_format($cost, 4) . "\n\n";
    }

} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
