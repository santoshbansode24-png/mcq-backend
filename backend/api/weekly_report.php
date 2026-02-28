<?php
/**
 * Weekly Progress Report — Cron Endpoint
 * Veeru App
 *
 * Called every Sunday by cron-job.org (or manually from admin panel)
 * Sends a personalized WhatsApp progress report to every user.
 *
 * Security: Protected by CRON_SECRET env variable.
 * Usage:
 *   GET /backend/api/weekly_report.php?secret=YOUR_SECRET           → send to all users
 *   GET /backend/api/weekly_report.php?secret=YOUR_SECRET&dry_run=1 → preview only
 *   GET /backend/api/weekly_report.php?secret=YOUR_SECRET&user_id=5 → send to one user
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';
require_once '../config/sms_config.php';
require_once '../services/TwilioService.php';

header('Content-Type: application/json');

// ─── Security Check ───────────────────────────────────────────────────────────
$expectedSecret = getenv('CRON_SECRET') ?: 'veeru_weekly_2026';
$providedSecret = $_GET['secret'] ?? '';

if ($providedSecret !== $expectedSecret) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$dryRun   = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
$onlyUser = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// ─── Fetch Users ──────────────────────────────────────────────────────────────
try {
    if ($onlyUser) {
        $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone FROM users WHERE user_id = ? AND (mobile IS NOT NULL OR phone IS NOT NULL)");
        $stmt->execute([$onlyUser]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone FROM users WHERE mobile IS NOT NULL OR phone IS NOT NULL");
        $stmt->execute();
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

$twilio  = new TwilioService();
$results = [];

foreach ($users as $user) {
    $userId    = $user['user_id'];
    $userName  = $user['name'] ?? 'Student';
    $userPhone = !empty($user['mobile']) ? $user['mobile'] : $user['phone'];

    // ── 1. Overall Syllabus Progress ──────────────────────────────────────
    try {
        // Total content sets (MCQ + flashcard) across all chapters the user has access to
        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM content_progress
            WHERE user_id = ?
        ");
        $stmtTotal->execute([$userId]);
        $totalSets = (int)($stmtTotal->fetch()['total'] ?? 0);

        $stmtDone = $pdo->prepare("
            SELECT COUNT(*) as done
            FROM content_progress
            WHERE user_id = ? AND status = 'completed'
        ");
        $stmtDone->execute([$userId]);
        $completedSets = (int)($stmtDone->fetch()['done'] ?? 0);

        $remainingSets      = max(0, $totalSets - $completedSets);
        $overallPct         = $totalSets > 0 ? round(($completedSets / $totalSets) * 100) : 0;

        // ── 2. This Week's MCQ Scores ─────────────────────────────────────
        $weekStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stmtWeek  = $pdo->prepare("
            SELECT 
                c.chapter_name,
                COUNT(ma.attempt_id)                                          AS total_attempts,
                SUM(CASE WHEN ma.is_correct = 1 THEN 1 ELSE 0 END)           AS correct_answers,
                ROUND(SUM(CASE WHEN ma.is_correct = 1 THEN 1 ELSE 0 END) * 100.0 
                      / COUNT(ma.attempt_id), 0)                              AS score_pct
            FROM mcq_attempts ma
            JOIN chapters c ON ma.chapter_id = c.chapter_id
            WHERE ma.user_id = ? AND ma.attempted_at >= ?
            GROUP BY ma.chapter_id, c.chapter_name
            ORDER BY ma.attempted_at DESC
            LIMIT 5
        ");
        $stmtWeek->execute([$userId, $weekStart]);
        $weeklyScores = $stmtWeek->fetchAll(PDO::FETCH_ASSOC);

        // ── 3. Build the WhatsApp Message ─────────────────────────────────
        $message = buildWeeklyMessage($userName, $overallPct, $completedSets, $remainingSets, $weeklyScores);

        // ── 4. Send or Dry-Run ────────────────────────────────────────────
        if ($dryRun) {
            $results[] = [
                'user_id'   => $userId,
                'name'      => $userName,
                'phone'     => $userPhone,
                'message'   => $message,
                'sent'      => false,
                'dry_run'   => true,
            ];
        } else {
            $sent = $twilio->sendWhatsAppNotification($userPhone, $message);
            $results[] = [
                'user_id' => $userId,
                'name'    => $userName,
                'phone'   => substr($userPhone, 0, 4) . '****',
                'sent'    => $sent,
            ];
        }

    } catch (PDOException $e) {
        $results[] = [
            'user_id' => $userId,
            'name'    => $userName,
            'error'   => $e->getMessage(),
        ];
    }
}

echo json_encode([
    'status'     => 'success',
    'dry_run'    => $dryRun,
    'total_users'=> count($users),
    'results'    => $results,
]);

// ─── Message Builder ──────────────────────────────────────────────────────────
function buildWeeklyMessage($name, $overallPct, $completed, $remaining, $weeklyScores) {
    $emoji      = $overallPct >= 80 ? '🔥' : ($overallPct >= 50 ? '📈' : '💪');
    $motiveLine = getMotivationalLine($overallPct);

    $msg  = "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📚 *Weekly Report — Veeru App*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Hi *{$name}*! Here's your week:\n\n";

    $msg .= "📊 *Syllabus Progress*\n";
    $msg .= "✅ Completed: *{$completed} sets* ({$overallPct}%)\n";
    $msg .= "📌 Remaining: *{$remaining} sets*\n";

    // Progress bar visual
    $filled  = (int)($overallPct / 10);
    $empty   = 10 - $filled;
    $bar     = str_repeat('█', $filled) . str_repeat('░', $empty);
    $msg    .= "  [{$bar}] {$overallPct}%\n\n";

    if (!empty($weeklyScores)) {
        $msg .= "🏆 *This Week's Scores*\n";
        foreach ($weeklyScores as $s) {
            $scoreEmoji = $s['score_pct'] >= 80 ? '🌟' : ($s['score_pct'] >= 60 ? '👍' : '📝');
            $chName     = strlen($s['chapter_name']) > 25
                          ? substr($s['chapter_name'], 0, 22) . '...'
                          : $s['chapter_name'];
            $msg       .= "  {$scoreEmoji} {$chName}: *{$s['score_pct']}%*\n";
        }
        $msg .= "\n";
    } else {
        $msg .= "📝 *No MCQs attempted this week.*\n";
        $msg .= "Try a chapter today — 10 mins a day makes a difference!\n\n";
    }

    $msg .= "{$emoji} {$motiveLine}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🎯 Open Veeru App to continue →";

    return $msg;
}

function getMotivationalLine($pct) {
    if ($pct >= 90) return "Outstanding! You're almost there. Keep it up!";
    if ($pct >= 70) return "Great progress! You're in the top league.";
    if ($pct >= 50) return "Halfway there — keep the momentum going!";
    if ($pct >= 30) return "Good start! Consistency is the key to success.";
    return "Every journey starts with a single step. Let's go!";
}
?>
