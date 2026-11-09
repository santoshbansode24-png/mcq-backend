<?php
/**
 * Get Scholarship Live Exams API
 * Returns Live, Upcoming, and Past Scholarship Exams
 * Veeru API
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=UTF-8');

// Inject CORS Headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 38;

try {
    // 1. Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `exams` (
            `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `class_id` INT NOT NULL,
            `subject_id` INT DEFAULT NULL,
            `duration_minutes` INT DEFAULT 60,
            `positive_marks` DECIMAL(5,2) DEFAULT 2.00,
            `negative_marks` DECIMAL(5,2) DEFAULT 0.00,
            `total_questions` INT DEFAULT 0,
            `is_live` TINYINT(1) DEFAULT 0,
            `is_scholarship` TINYINT(1) DEFAULT 1,
            `start_time` DATETIME DEFAULT NULL,
            `end_time` DATETIME DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `question_ids` MEDIUMTEXT DEFAULT NULL,
            `selected_chapters` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`class_id`),
            INDEX (`is_live`),
            INDEX (`is_scholarship`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $now = date('Y-m-d H:i:s');

    // Fetch all scholarship exams
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name,
               (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as total_participants
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.class_id
        WHERE e.is_scholarship = 1
        ORDER BY 
            CASE 
                WHEN e.is_live = 1 AND (e.start_time IS NULL OR (e.start_time <= ? AND (e.end_time IS NULL OR e.end_time >= ?))) THEN 1
                WHEN e.is_live = 1 AND e.start_time > ? THEN 2
                ELSE 3 
            END,
            e.start_time ASC,
            e.exam_id DESC
    ");
    $stmt->execute([$now, $now, $now]);
    $allExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If user_id provided, fetch user's attempts for these exams
    $userAttempts = [];
    if ($user_id > 0) {
        $stmtAttempts = $pdo->prepare("
            SELECT ea.*, 
                   (SELECT COUNT(*) + 1 FROM exam_attempts ea2 
                    WHERE ea2.exam_id = ea.exam_id AND (ea2.net_score > ea.net_score OR (ea2.net_score = ea.net_score AND ea2.time_spent_seconds < ea.time_spent_seconds))) as user_rank
            FROM exam_attempts ea
            WHERE ea.user_id = ?
            ORDER BY ea.net_score DESC, ea.attempt_id DESC
        ");
        $stmtAttempts->execute([$user_id]);
        while ($att = $stmtAttempts->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($userAttempts[$att['exam_id']])) {
                $userAttempts[$att['exam_id']] = $att;
            }
        }
    }

    $spotlight = null;
    $live_and_upcoming = [];
    $past_exams = [];

    foreach ($allExams as $exam) {
        $exam_id = $exam['exam_id'];
        $has_attempted = isset($userAttempts[$exam_id]);
        $attempt_data = $has_attempted ? $userAttempts[$exam_id] : null;

        $exam['has_attempted'] = $has_attempted;
        $exam['user_score'] = $attempt_data ? floatval($attempt_data['net_score']) : null;
        $exam['user_rank'] = $attempt_data ? intval($attempt_data['user_rank']) : null;
        $exam['user_accuracy'] = $attempt_data ? floatval($attempt_data['accuracy_percentage']) : null;
        $exam['time_spent_seconds'] = $attempt_data ? intval($attempt_data['time_spent_seconds']) : null;

        $is_live_now = false;
        $is_upcoming = false;
        $is_past = false;

        if (!$exam['is_live']) {
            $is_past = true;
        } elseif (!empty($exam['start_time']) && !empty($exam['end_time'])) {
            if ($now >= $exam['start_time'] && $now <= $exam['end_time']) {
                $is_live_now = true;
            } elseif ($now < $exam['start_time']) {
                $is_upcoming = true;
            } else {
                $is_past = true;
            }
        } elseif ($exam['is_live']) {
            $is_live_now = true;
        }

        $exam['status'] = $is_live_now ? 'live_now' : ($is_upcoming ? 'upcoming' : 'completed');

        // Seconds until start
        if ($is_upcoming && !empty($exam['start_time'])) {
            $exam['seconds_to_start'] = max(0, strtotime($exam['start_time']) - time());
        } elseif ($is_live_now && !empty($exam['end_time'])) {
            $exam['seconds_to_end'] = max(0, strtotime($exam['end_time']) - time());
        }

        // WhatsApp Share Link & Text
        $shareText = "🏆 *महाराष्ट्र राज्य शिष्यवृत्ती सराव परीक्षा*\n" .
                     "📝 *{$exam['title']}*\n" .
                     "⏱️ वेळ: {$exam['duration_minutes']} मिनिटे | प्रश्न: {$exam['total_questions']}\n" .
                     "📅 दिनांक: " . (!empty($exam['start_time']) ? date('d M Y, h:i A', strtotime($exam['start_time'])) : 'लवकरच') . "\n" .
                     "👉 परीक्षेत भाग घेण्यासाठी व राज्यस्तरीय रँक पाहण्यासाठी खालील लिंकवर क्लिक करा:\n" .
                     "https://veeru.app/exam?id={$exam_id}";

        $exam['share_text'] = $shareText;
        $exam['deep_link'] = "veeru://exam/live/{$exam_id}";

        // Pick top spotlight
        if (!$spotlight && ($is_live_now || $is_upcoming)) {
            $spotlight = $exam;
        }

        if ($is_live_now || $is_upcoming) {
            $live_and_upcoming[] = $exam;
        } else {
            $past_exams[] = $exam;
        }
    }

    sendResponse('success', 'Exams fetched successfully', [
        'spotlight_exam' => $spotlight,
        'live_and_upcoming' => $live_and_upcoming,
        'past_exams' => $past_exams,
        'total_exams' => count($allExams)
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Error fetching exams: ' . $e->getMessage(), null, 500);
}
