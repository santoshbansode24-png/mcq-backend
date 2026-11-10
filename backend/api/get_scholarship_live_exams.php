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
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';

// Master list of competitive categories
$CATEGORIES_MAP = [
    'scholarship' => [
        'id' => 'scholarship',
        'title' => 'राज्य शिष्यवृत्ती',
        'full_name' => 'महाराष्ट्र राज्य शिष्यवृत्ती परीक्षा (5th & 8th)',
        'badge' => '📜 राज्य शिष्यवृत्ती',
        'icon' => 'ribbon-outline',
        'color' => '#4F46E5',
        'info' => 'PUP (५वी) व PSS (८वी) पेपर १ व पेपर २ सराव'
    ],
    'navodaya' => [
        'id' => 'navodaya',
        'title' => 'नवोदय (JNVST)',
        'full_name' => 'जवाहर नवोदय विद्यालय प्रवेश परीक्षा',
        'badge' => '🏛️ नवोदय विद्यालय',
        'icon' => 'school-outline',
        'color' => '#0284C7',
        'info' => 'मानसिक क्षमता (५०%), अंकगणित (२५%), भाषा (२५%) - ८० प्रश्न | १०० गुण'
    ],
    'sainik' => [
        'id' => 'sainik',
        'title' => 'सैनिक स्कूल (AISSEE)',
        'full_name' => 'अखिल भारतीय सैनिक शाळा प्रवेश परीक्षा',
        'badge' => '🎖️ सैनिक शाळा',
        'icon' => 'shield-checkmark-outline',
        'color' => '#15803D',
        'info' => 'गणित (१५० गुण), बुद्धिमत्ता (५० गुण), भाषा (५० गुण), GK (५० गुण)'
    ],
    'olympiad' => [
        'id' => 'olympiad',
        'title' => 'ऑलिम्पियाड',
        'full_name' => 'ऑलिम्पियाड स्पर्धा परीक्षा (SOF/Homi Bhabha)',
        'badge' => '🏆 ऑलिम्पियाड',
        'icon' => 'trophy-outline',
        'color' => '#D97706',
        'info' => 'IMO (गणित), NSO (विज्ञान), Achievers Section (HOTS) दुप्पट गुणांसह'
    ],
    'mts' => [
        'id' => 'mts',
        'title' => 'MTS / NMMS',
        'full_name' => 'महाराष्ट्र टॅलेंट सर्च व NMMS प्रज्ञाशोध परीक्षा',
        'badge' => '💡 MTS / NMMS',
        'icon' => 'bulb-outline',
        'color' => '#7C3AED',
        'info' => 'MAT (मानसिक क्षमता) + SAT (शालेय अभियोग्यता)'
    ]
];

try {
    // 1. Ensure table and category_slug column exist
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
            `category_slug` VARCHAR(50) DEFAULT 'scholarship',
            `start_time` DATETIME DEFAULT NULL,
            `end_time` DATETIME DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `question_ids` MEDIUMTEXT DEFAULT NULL,
            `selected_chapters` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`class_id`),
            INDEX (`is_live`),
            INDEX (`is_scholarship`),
            INDEX (`category_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $pdo->exec("ALTER TABLE `exams` ADD COLUMN `category_slug` VARCHAR(50) DEFAULT 'scholarship'");
    } catch (Exception $e) {}

    $now = date('Y-m-d H:i:s');

    // Filter by category if requested
    $whereClause = "WHERE e.is_scholarship = 1";
    $whereParams = [];

    if (!empty($category) && $category !== 'all') {
        $whereClause .= " AND (e.category_slug = ? OR (e.category_slug IS NULL AND ? = 'scholarship'))";
        $whereParams[] = $category;
        $whereParams[] = $category;
    }

    $orderParams = [$now, $now, $now];
    $queryParams = array_merge($whereParams, $orderParams);

    // Fetch scholarship/competitive exams
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name,
               (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as total_participants
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.class_id
        $whereClause
        ORDER BY 
            CASE 
                WHEN e.is_live = 1 AND (e.start_time IS NULL OR (e.start_time <= ? AND (e.end_time IS NULL OR e.end_time >= ?))) THEN 1
                WHEN e.is_live = 1 AND e.start_time > ? THEN 2
                ELSE 3 
            END,
            e.start_time ASC,
            e.exam_id DESC
    ");
    $stmt->execute($queryParams);
    $allExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch counts per category for UI badge counts
    $catCounts = ['all' => 0];
    foreach (array_keys($CATEGORIES_MAP) as $ck) {
        $catCounts[$ck] = 0;
    }
    try {
        $countStmt = $pdo->query("
            SELECT COALESCE(category_slug, 'scholarship') as cat, COUNT(*) as cnt 
            FROM exams 
            WHERE is_scholarship = 1 
            GROUP BY COALESCE(category_slug, 'scholarship')
        ");
        $allCount = 0;
        while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
            $cslug = $row['cat'];
            $cnt = intval($row['cnt']);
            $catCounts[$cslug] = $cnt;
            $allCount += $cnt;
        }
        $catCounts['all'] = $allCount;
    } catch (Exception $e) {}

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

        $catSlug = !empty($exam['category_slug']) ? $exam['category_slug'] : 'scholarship';
        $catInfo = isset($CATEGORIES_MAP[$catSlug]) ? $CATEGORIES_MAP[$catSlug] : $CATEGORIES_MAP['scholarship'];

        $exam['category_slug'] = $catSlug;
        $exam['category_name'] = $catInfo['title'];
        $exam['category_badge'] = $catInfo['badge'];
        $exam['category_color'] = $catInfo['color'];

        // WhatsApp Share Link & Text
        $shareText = "🏆 *{$catInfo['full_name']}*\n" .
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
        'total_exams' => count($allExams),
        'categories' => array_values($CATEGORIES_MAP),
        'category_counts' => $catCounts,
        'selected_category' => $category
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Error fetching exams: ' . $e->getMessage(), null, 500);
}
