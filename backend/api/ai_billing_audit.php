<?php
/**
 * AI BILLING & USAGE AUDIT TOOL
 * ----------------------------
 * This tool allows the admin to track AI spending by date.
 * Access via: https://api.veeruapp.in/backend/api/ai_billing_audit.php
 */
require_once __DIR__ . '/../config/db.php';

// Security Check: Only allow logged-in administrators
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin/index.php');
    exit();
}

global $pdo;

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$last7Days = date('Y-m-d', strtotime('-7 days'));

try {
    // 1. Get Summary Stats
    $query = "SELECT 
                usage_date, 
                SUM(tokens_used) as total_tokens, 
                SUM(request_count) as total_requests,
                COUNT(DISTINCT user_id) as active_users
              FROM ai_usage 
              WHERE usage_date >= ? 
              GROUP BY usage_date 
              ORDER BY usage_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$last7Days]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pricing Constants (Gemini 1.5/2.5 Flash)
    // Approx $0.15 per 1M tokens (average mix of input $0.075 and output $0.30)
    // 0.15 * 83.5 (USD to INR) = ₹12.5 per 1M tokens
    $PRICE_PER_MILLION_INR = 12.5;

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Veeru AI Billing Audit</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; padding: 40px; color: #333; }
            .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            h1 { color: #4f46e5; border-bottom: 2px solid #eef2ff; padding-bottom: 15px; margin-bottom: 25px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #f8fafc; text-align: left; padding: 15px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 600; }
            td { padding: 15px; border-bottom: 1px solid #f1f5f9; }
            .cost-positive { color: #059669; font-weight: bold; }
            .date-highlight { font-weight: bold; color: #1e293b; }
            .stats-card { display: flex; gap: 20px; margin-bottom: 30px; }
            .card { flex: 1; background: #eef2ff; padding: 20px; border-radius: 12px; border: 1px solid #e0e7ff; }
            .card h3 { margin: 0; font-size: 14px; color: #6366f1; text-transform: uppercase; letter-spacing: 1px; }
            .card p { margin: 10px 0 0; font-size: 24px; font-weight: bold; color: #4338ca; }
            .footer { margin-top: 30px; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Veeru AI Usage & Billing Audit</h1>
            
            <div class="stats-card">
                <div class="card">
                    <h3>Today's Spend</h3>
                    <p>₹<?php 
                        $todayData = array_filter($results, function($r) use ($today) { return $r['usage_date'] == $today; });
                        $todayTokens = !empty($todayData) ? array_values($todayData)[0]['total_tokens'] : 0;
                        echo number_format(($todayTokens / 1000000) * $PRICE_PER_MILLION_INR, 2);
                    ?></p>
                </div>
                <div class="card">
                    <h3>Total Credit Balance</h3>
                    <p>₹488.47</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Students</th>
                        <th>Total Requests</th>
                        <th>Tokens Consumed</th>
                        <th>Estimated Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): 
                        $isToday = $row['usage_date'] == $today;
                        $cost = ($row['total_tokens'] / 1000000) * $PRICE_PER_MILLION_INR;
                    ?>
                    <tr>
                        <td class="date-highlight"><?php echo $row['usage_date'] . ($isToday ? " (Today)" : ""); ?></td>
                        <td><?php echo $row['active_users']; ?></td>
                        <td><?php echo number_format($row['total_requests']); ?></td>
                        <td><?php echo number_format($row['total_tokens']); ?></td>
                        <td class="cost-positive">₹<?php echo number_format($cost, 3); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer">
                * Estimated cost based on Gemini 1.5 Flash current pricing (~$0.075/1M tokens). Actual Google Cloud billing may vary slightly due to input vs output token ratios.
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "<h1>Billing Audit Error</h1><p>" . $e->getMessage() . "</p>";
}
