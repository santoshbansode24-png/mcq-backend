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
        $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone, class_id FROM users WHERE user_id = ? AND (mobile IS NOT NULL OR phone IS NOT NULL)");
        $stmt->execute([$onlyUser]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone, class_id FROM users WHERE mobile IS NOT NULL OR phone IS NOT NULL");
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
    $classId   = $user['class_id'] ?? null;

    try {
        // ── 1. Chapter-Level Progress (from student_progress — real source of truth) ──
        $stmtChapterStats = $pdo->prepare("
            SELECT
                COUNT(DISTINCT ch.chapter_id)                              AS total_chapters,
                COUNT(DISTINCT sp.chapter_id)                             AS completed_chapters,
                ROUND(AVG(sp_best.best_pct), 0)                          AS avg_score
            FROM chapters ch
            JOIN subjects s ON ch.subject_id = s.subject_id
            LEFT JOIN student_progress sp
                ON ch.chapter_id = sp.chapter_id AND sp.user_id = ?
            LEFT JOIN (
                SELECT chapter_id, MAX(percentage) as best_pct
                FROM student_progress
                WHERE user_id = ?
                GROUP BY chapter_id
            ) sp_best ON ch.chapter_id = sp_best.chapter_id
            WHERE s.class_id = ?
        ");
        $stmtChapterStats->execute([$userId, $userId, $classId]);
        $chapterStats = $stmtChapterStats->fetch();

        $totalChapters     = (int)($chapterStats['total_chapters']     ?? 0);
        $completedChapters = (int)($chapterStats['completed_chapters'] ?? 0);
        $remainingChapters = max(0, $totalChapters - $completedChapters);
        $overallPct        = $totalChapters > 0
                             ? round(($completedChapters / $totalChapters) * 100)
                             : 0;

        // ── 2. This Week's Scores (from student_progress, last 7 days) ────────
        $weekStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stmtWeek  = $pdo->prepare("
            SELECT
                ch.chapter_name,
                MAX(sp.percentage) AS score_pct,
                sp.mcq_score,
                sp.total_mcq
            FROM student_progress sp
            JOIN chapters ch ON sp.chapter_id = ch.chapter_id
            WHERE sp.user_id = ? AND sp.completed_at >= ?
            GROUP BY sp.chapter_id, ch.chapter_name, sp.mcq_score, sp.total_mcq
            ORDER BY MAX(sp.completed_at) DESC
            LIMIT 5
        ");
        $stmtWeek->execute([$userId, $weekStart]);
        $weeklyScores = $stmtWeek->fetchAll(PDO::FETCH_ASSOC);

        // ── 4. Build the WhatsApp Message ─────────────────────────────────
        $message = buildWeeklyMessage(
            $userName,
            $overallPct,
            $completedChapters,
            $totalChapters,
            $remainingChapters,
            $weeklyScores
        );

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
function buildWeeklyMessage(
    $name, $overallPct,
    $completedChapters, $totalChapters, $remainingChapters,
    $weeklyScores
) {
    $emoji      = $overallPct >= 80 ? '🔥' : ($overallPct >= 50 ? '📈' : '💪');
    $motiveLine = getMotivationalLine($overallPct);

    $msg  = "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📚 *Weekly Report — Veeru App*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Hi *{$name}*! Here's your week:\n\n";

    // ── Chapter-Level Progress ────────────────────────────────────────────
    $msg .= "📖 *Chapter Progress*\n";
    $msg .= "✅ Completed: *{$completedChapters} of {$totalChapters} chapters*\n";
    $msg .= "📌 Remaining: *{$remainingChapters} chapter" . ($remainingChapters !== 1 ? 's' : '') . " to complete*\n\n";

    // ── Overall % Progress Bar ────────────────────────────────────────────
    $msg .= "📊 *Overall Syllabus*\n";
    $filled = (int)($overallPct / 10);
    $empty  = 10 - $filled;
    $bar    = str_repeat('█', $filled) . str_repeat('░', $empty);
    $msg   .= "  [{$bar}] *{$overallPct}% done*\n\n";

    // ── This Week's Scores ────────────────────────────────────────────────
    if (!empty($weeklyScores)) {
        $msg .= "🏆 *This Week's Scores*\n";
        foreach ($weeklyScores as $s) {
            $scoreEmoji = $s['score_pct'] >= 80 ? '🌟' : ($s['score_pct'] >= 60 ? '👍' : '📝');
            $chName     = strlen($s['chapter_name']) > 22
                          ? substr($s['chapter_name'], 0, 20) . '...'
                          : $s['chapter_name'];
            $msg       .= "  {$scoreEmoji} {$chName}: *{$s['score_pct']}%*\n";
        }
        $msg .= "\n";
    } else {
        $msg .= "📝 *No quizzes this week.*\n";
        $msg .= "Try a chapter today — just 10 mins a day!\n\n";
    }

    $msg .= "{$emoji} _{$motiveLine}_\n";
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
