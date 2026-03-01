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
        // Single user — exact user_id
        $stmt = $pdo->prepare("SELECT user_id, name, mobile, phone, class_id FROM users WHERE user_id = ? AND (mobile IS NOT NULL OR phone IS NOT NULL)");
        $stmt->execute([$onlyUser]);
    } else {
        // All users — deduplicate by phone number, pick the account with MOST content_progress data.
        // Uses WHERE correlated subquery to avoid GROUP BY (incompatible with only_full_group_by on Railway).
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.mobile, u.phone, u.class_id
            FROM users u
            WHERE (u.mobile IS NOT NULL OR u.phone IS NOT NULL)
              AND u.user_id = (
                  SELECT u2.user_id
                  FROM users u2
                  WHERE COALESCE(u2.mobile, u2.phone) = COALESCE(u.mobile, u.phone)
                  ORDER BY (
                      SELECT COUNT(*) FROM content_progress cp WHERE cp.user_id = u2.user_id
                  ) DESC, u2.user_id ASC
                  LIMIT 1
              )
        ");
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
        // ── 1. Chapter completion via content_progress ──────────────────
        // content_progress is written by mark_set_completed.php (what the app calls)
        // A chapter is "completed" = it has AT LEAST ONE completed set in content_progress
        $stmtChapters = $pdo->prepare("
            SELECT
                COUNT(DISTINCT ch.chapter_id)                         AS total_chapters,
                COUNT(DISTINCT CASE WHEN cp.status = 'completed'
                               THEN cp.chapter_id END)                AS completed_chapters
            FROM chapters ch
            JOIN subjects s ON ch.subject_id = s.subject_id
            LEFT JOIN content_progress cp
                ON ch.chapter_id = cp.chapter_id AND cp.user_id = ?
            WHERE s.class_id = ?
        ");
        $stmtChapters->execute([$userId, $classId]);
        $chapRow = $stmtChapters->fetch();

        $totalChapters     = (int)($chapRow['total_chapters']     ?? 0);
        $completedChapters = (int)($chapRow['completed_chapters'] ?? 0);
        $remainingChapters = max(0, $totalChapters - $completedChapters);
        $overallPct        = $totalChapters > 0
                             ? round(($completedChapters / $totalChapters) * 100)
                             : 0;

        // ── 2. This week's scores from content_progress ─────────────────
        $weekStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stmtWeek  = $pdo->prepare("
            SELECT
                ch.chapter_name,
                MAX(ROUND(cp.score * 100.0 / NULLIF(cp.total, 0), 0)) AS score_pct
            FROM content_progress cp
            JOIN chapters ch ON cp.chapter_id = ch.chapter_id
            WHERE cp.user_id = ?
              AND cp.content_type = 'mcq'
              AND cp.updated_at >= ?
              AND cp.total > 0
            GROUP BY cp.chapter_id, ch.chapter_name
            ORDER BY MAX(cp.updated_at) DESC
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
            $logoUrl = 'https://api.veeruapp.in/backend/public/veeru_logo.png';
            $sent = $twilio->sendWhatsAppNotificationWithMedia($userPhone, $message, $logoUrl);
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
    $msg .= "🎯 *Open Veeru App to continue* →\n";
    $msg .= "https://play.google.com/store/apps/details?id=com.veeru.app";

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
