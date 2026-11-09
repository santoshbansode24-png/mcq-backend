<?php
/**
 * Admin Scholarship Live Exam Management
 * Veeru
 */
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once '../config/db.php';

// Self-healing schema check
try {
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

    $cols = $pdo->query("SHOW COLUMNS FROM `exams`")->fetchAll(PDO::FETCH_COLUMN);
    $needed = [
        'is_live' => "ALTER TABLE `exams` ADD COLUMN `is_live` TINYINT(1) DEFAULT 0",
        'is_scholarship' => "ALTER TABLE `exams` ADD COLUMN `is_scholarship` TINYINT(1) DEFAULT 1",
        'start_time' => "ALTER TABLE `exams` ADD COLUMN `start_time` DATETIME DEFAULT NULL",
        'end_time' => "ALTER TABLE `exams` ADD COLUMN `end_time` DATETIME DEFAULT NULL",
        'instructions' => "ALTER TABLE `exams` ADD COLUMN `instructions` TEXT DEFAULT NULL",
        'question_ids' => "ALTER TABLE `exams` ADD COLUMN `question_ids` MEDIUMTEXT DEFAULT NULL",
        'selected_chapters' => "ALTER TABLE `exams` ADD COLUMN `selected_chapters` TEXT DEFAULT NULL"
    ];
    foreach ($needed as $col => $sql) {
        if (!in_array($col, $cols)) {
            try { $pdo->exec($sql); } catch (Exception $e) {}
        }
    }
} catch (Exception $e) {}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_exam') {
        $title = trim($_POST['title'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 38);
        $duration = intval($_POST['duration_minutes'] ?? 60);
        $pos_marks = floatval($_POST['positive_marks'] ?? 2.00);
        $neg_marks = floatval($_POST['negative_marks'] ?? 0.00);
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        $instructions = trim($_POST['instructions'] ?? '');
        $chapter_ids = $_POST['chapter_ids'] ?? [];
        $total_questions_limit = intval($_POST['question_limit'] ?? 50);

        if (empty($title)) {
            $error = 'Exam Title is required.';
        } else {
            try {
                // Auto-pick questions from selected chapters
                $picked_mcq_ids = [];
                if (!empty($chapter_ids)) {
                    $inPlaceholders = implode(',', array_fill(0, count($chapter_ids), '?'));
                    $stmtMCQ = $pdo->prepare("SELECT mcq_id FROM mcqs WHERE chapter_id IN ($inPlaceholders)");
                    $stmtMCQ->execute($chapter_ids);
                    $all_mcqs = $stmtMCQ->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($all_mcqs)) {
                        shuffle($all_mcqs);
                        $picked_mcq_ids = array_slice($all_mcqs, 0, $total_questions_limit);
                    }
                }

                $question_ids_str = implode(',', $picked_mcq_ids);
                $chapter_ids_str = implode(',', $chapter_ids);
                $total_q = count($picked_mcq_ids);

                $stmtInsert = $pdo->prepare("
                    INSERT INTO exams 
                    (title, class_id, duration_minutes, positive_marks, negative_marks, total_questions, is_live, is_scholarship, start_time, end_time, instructions, question_ids, selected_chapters, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtInsert->execute([
                    $title, $class_id, $duration, $pos_marks, $neg_marks, $total_q,
                    $start_time, $end_time, $instructions, $question_ids_str, $chapter_ids_str
                ]);

                $message = "Scholarship Live Exam '$title' created successfully with $total_q questions!";
            } catch (Exception $e) {
                $error = "Failed to create exam: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_live') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        $current_status = intval($_POST['current_status'] ?? 0);
        $new_status = $current_status ? 0 : 1;
        try {
            $pdo->prepare("UPDATE exams SET is_live = ? WHERE exam_id = ?")->execute([$new_status, $exam_id]);
            $message = "Live status updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif ($action === 'delete_exam') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM exams WHERE exam_id = ?")->execute([$exam_id]);
            $message = "Exam deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting exam: " . $e->getMessage();
        }
    }
}

// Fetch all classes for selection
$classes = [];
try {
    $classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_id ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Scholarship Chapters (subjects related to scholarship or all)
$chapters = [];
try {
    $chapters = $pdo->query("
        SELECT ch.chapter_id, ch.chapter_name, s.subject_name, c.class_name,
               (SELECT COUNT(*) FROM mcqs m WHERE m.chapter_id = ch.chapter_id) as mcq_count
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE c.class_name LIKE '%Scholarship%' OR s.subject_name LIKE '%Scholarship%' OR c.class_id IN (37, 38)
        ORDER BY s.subject_name, ch.chapter_name
    ")->fetchAll();
    
    // If no scholarship-specific chapters tagged, load all chapters as fallback
    if (empty($chapters)) {
        $chapters = $pdo->query("
            SELECT ch.chapter_id, ch.chapter_name, s.subject_name, c.class_name,
                   (SELECT COUNT(*) FROM mcqs m WHERE m.chapter_id = ch.chapter_id) as mcq_count
            FROM chapters ch
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            ORDER BY c.class_name, s.subject_name, ch.chapter_name
            LIMIT 100
        ")->fetchAll();
    }
} catch (Exception $e) {}

// Fetch all scholarship exams
$exams = [];
try {
    $exams = $pdo->query("
        SELECT e.*, c.class_name,
               (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as total_attempts,
               (SELECT AVG(ea.net_score) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as avg_score
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.class_id
        WHERE e.is_scholarship = 1
        ORDER BY e.exam_id DESC
    ")->fetchAll();
} catch (Exception $e) {}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$board_name = $_SESSION['board_name'] ?? 'Scholarship';
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Live Exams - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 18px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header h1 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header .btn-back { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 10px; overflow-x: auto; }
        .nav li a { display: block; padding: 14px 18px; color: #666; text-decoration: none; font-weight: 500; font-size: 14px; border-bottom: 3px solid transparent; }
        .nav li a.active, .nav li a:hover { color: #667eea; border-bottom-color: #667eea; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 30px; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 15px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .btn-create { background: linear-gradient(135deg, #ff7675, #d63031); color: white; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(214, 48, 49, 0.3); transition: 0.2s; }
        .btn-create:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(214, 48, 49, 0.4); }
        .exams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 24px; }
        .exam-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #eaeaea; display: flex; flex-direction: column; justify-content: space-between; position: relative; }
        .exam-card.is-live { border-top: 5px solid #00b894; }
        .exam-card.is-upcoming { border-top: 5px solid #0984e3; }
        .exam-card.is-past { border-top: 5px solid #b2bec3; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 12px; }
        .badge-live { background: #e6fffa; color: #00b894; border: 1px solid #00b894; }
        .badge-upcoming { background: #ebf8ff; color: #0984e3; border: 1px solid #0984e3; }
        .badge-inactive { background: #f1f2f6; color: #747d8c; }
        .exam-title { font-size: 18px; font-weight: 700; color: #2d3436; margin-bottom: 8px; line-height: 1.4; }
        .exam-meta { font-size: 13px; color: #636e72; margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
        .exam-meta span { display: flex; align-items: center; gap: 6px; }
        .stats-row { display: flex; justify-content: space-between; background: #f8f9fa; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; text-align: center; }
        .stat-item .num { font-size: 18px; font-weight: 700; color: #2d3436; }
        .stat-item .label { font-size: 11px; color: #888; font-weight: 600; }
        .card-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-whatsapp { background: #25d366; color: white; }
        .btn-toggle { background: #f1f2f6; color: #2d3436; }
        .btn-delete { background: #ffeaa7; color: #d63031; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; overflow-y: auto; padding: 40px 20px; }
        .modal-content { background: white; max-width: 750px; margin: auto; border-radius: 16px; padding: 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f2f5; padding-bottom: 16px; margin-bottom: 24px; }
        .modal-header h2 { font-size: 20px; color: #2d3436; }
        .close-btn { font-size: 24px; cursor: pointer; color: #888; border: none; background: none; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #2d3436; }
        .form-control { width: 100%; padding: 12px 14px; border: 1.5px solid #dfe6e9; border-radius: 8px; font-size: 14px; transition: 0.2s; }
        .form-control:focus { outline: none; border-color: #667eea; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .chapter-selector { max-height: 220px; overflow-y: auto; border: 1.5px solid #dfe6e9; border-radius: 8px; padding: 12px; background: #fafafa; }
        .chapter-item { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 13px; }
    </style>
</head>
<body>

<div class="header">
    <h1>🏆 शिष्यवृत्ती Live Exam व्यवस्थापन</h1>
    <a href="dashboard.php" class="btn-back">⬅ मुख्य डॅशबोर्ड</a>
</div>

<nav class="nav">
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="scholarship_exams.php" class="active">🏆 Scholarship Live Exams</a></li>
        <li><a href="mcqs.php">MCQs</a></li>
        <li><a href="subjects.php">Subjects</a></li>
        <li><a href="chapters.php">Chapters</a></li>
        <li><a href="users.php">Users</a></li>
    </ul>
</nav>

<div class="container">
    <?php if (!empty($message)): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="top-bar">
        <div>
            <h2>सर्व शिष्यवृत्ती लाईव्ह परीक्षा (Total: <?php echo count($exams); ?>)</h2>
            <p style="color: #666; font-size: 14px;">येथून नवीन परीक्षा शेड्युल करा, प्रश्न जोडा आणि थेट WhatsApp वर लिंक शेअर करा.</p>
        </div>
        <button onclick="openModal()" class="btn-create">➕ नवीन Live Exam तयार करा</button>
    </div>

    <?php if (empty($exams)): ?>
        <div style="background: white; padding: 60px; text-align: center; border-radius: 16px; color: #888;">
            <p style="font-size: 18px; margin-bottom: 16px;">अद्याप कोणतीही शिष्यवृत्ती परीक्षा तयार केलेली नाही.</p>
            <button onclick="openModal()" class="btn-create">पहिली परीक्षा तयार करा</button>
        </div>
    <?php else: ?>
        <div class="exams-grid">
            <?php foreach ($exams as $ex): 
                $now = date('Y-m-d H:i:s');
                $is_active = $ex['is_live'];
                $status_class = 'is-upcoming';
                $badge_text = 'Upcoming (येणारी परीक्षा)';
                $badge_class = 'badge-upcoming';

                if (!$is_active) {
                    $status_class = 'is-inactive';
                    $badge_text = 'Inactive (बंद)';
                    $badge_class = 'badge-inactive';
                } elseif (!empty($ex['start_time']) && !empty($ex['end_time'])) {
                    if ($now >= $ex['start_time'] && $now <= $ex['end_time']) {
                        $status_class = 'is-live';
                        $badge_text = '🔴 LIVE NOW (चालू आहे)';
                        $badge_class = 'badge-live';
                    } elseif ($now > $ex['end_time']) {
                        $status_class = 'is-past';
                        $badge_text = 'Completed (संपलेली)';
                        $badge_class = 'badge-inactive';
                    }
                } elseif ($is_active) {
                    $status_class = 'is-live';
                    $badge_text = '🟢 LIVE ACTIVE';
                    $badge_class = 'badge-live';
                }

                $shareText = "🏆 *महाराष्ट्र राज्य शिष्यवृत्ती सराव परीक्षा*\n" .
                             "📝 *{$ex['title']}*\n" .
                             "⏱️ वेळ: " . ($ex['duration_minutes'] ?? 60) . " मिनिटे | प्रश्न: " . ($ex['total_questions'] ?? 50) . "\n" .
                             "📅 दिनांक: " . (!empty($ex['start_time']) ? date('d M Y, h:i A', strtotime($ex['start_time'])) : 'लवकरच') . "\n" .
                             "👉 परीक्षेत भाग घेण्यासाठी व राज्यस्तरीय रँक पाहण्यासाठी खालील लिंकवर क्लिक करा:\n" .
                             "https://veeru.app/exam?id=" . $ex['exam_id'];
            ?>
                <div class="exam-card <?php echo $status_class; ?>">
                    <div>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                        <h3 class="exam-title"><?php echo htmlspecialchars($ex['title']); ?></h3>
                        
                        <div class="exam-meta">
                            <span>📚 <strong>इयत्ता:</strong> <?php echo htmlspecialchars($ex['class_name'] ?? 'Scholarship'); ?></span>
                            <span>⏱️ <strong>वेळ:</strong> <?php echo $ex['duration_minutes']; ?> मिनिटे | <strong>प्रश्न:</strong> <?php echo $ex['total_questions']; ?> प्रश्न</span>
                            <span>📅 <strong>सुरू वेळ:</strong> <?php echo !empty($ex['start_time']) ? date('d-m-Y h:i A', strtotime($ex['start_time'])) : 'शेड्युल केलेली नाही'; ?></span>
                            <span>🎯 <strong>गुण:</strong> प्रत्येक बरोबर उत्तर +<?php echo floatval($ex['positive_marks']); ?> गुण | निगेटिव्ह -<?php echo floatval($ex['negative_marks']); ?> गुण</span>
                        </div>

                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="num"><?php echo $ex['total_attempts']; ?></div>
                                <div class="label">परीक्षार्थी</div>
                            </div>
                            <div class="stat-item">
                                <div class="num"><?php echo round($ex['avg_score'] ?? 0, 1); ?></div>
                                <div class="label">सरासरी गुण</div>
                            </div>
                            <div class="stat-item">
                                <div class="num"><?php echo $ex['total_questions'] * $ex['positive_marks']; ?></div>
                                <div class="label">एकूण गुण</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions">
                        <button onclick="shareWhatsApp(<?php echo htmlspecialchars(json_encode($shareText)); ?>)" class="btn-action btn-whatsapp" title="WhatsApp वर पाठवा">
                            📲 WhatsApp शेअर
                        </button>
                        
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_live">
                            <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $ex['is_live']; ?>">
                            <button type="submit" class="btn-action btn-toggle">
                                <?php echo $ex['is_live'] ? '⏸️ बंद करा' : '▶️ Live करा'; ?>
                            </button>
                        </form>

                        <form method="POST" style="display:inline;" onsubmit="return confirm('खात्री आहे? ही परीक्षा डिलीट केल्यास सर्व रेकॉर्ड नष्ट होतील.');">
                            <input type="hidden" name="action" value="delete_exam">
                            <input type="hidden" name="exam_id" value="<?php echo $ex['exam_id']; ?>">
                            <button type="submit" class="btn-action btn-delete">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for Creating Exam -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ नवीन शिष्यवृत्ती Live Exam शेड्युल करा</h2>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_exam">

            <div class="form-group">
                <label>परीक्षेचे नाव (Exam Title) *</label>
                <input type="text" name="title" class="form-control" required placeholder="उदा. महाराष्ट्र राज्य ५वी शिष्यवृत्ती महा-सराव परीक्षा - १">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>इयत्ता / वर्ग (Class)</label>
                    <select name="class_id" class="form-control">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo ($c['class_id'] == 38 || strpos($c['class_name'], 'Scholarship') !== false) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>कालावधी (Duration in Minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="60" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>सुरू होण्याची तारीख व वेळ (Start DateTime)</label>
                    <input type="datetime-local" name="start_time" class="form-control">
                </div>
                <div class="form-group">
                    <label>समाप्तीची तारीख व वेळ (End DateTime)</label>
                    <input type="datetime-local" name="end_time" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>प्रत्येक अचूक प्रश्नाला गुण (Positive Marks)</label>
                    <input type="number" step="0.5" name="positive_marks" class="form-control" value="2.0">
                </div>
                <div class="form-group">
                    <label>चुकलेल्या प्रश्नाला निगेटिव्ह गुण (Negative Marks)</label>
                    <input type="number" step="0.25" name="negative_marks" class="form-control" value="0.0">
                </div>
            </div>

            <div class="form-group">
                <label>एकूण किती प्रश्न निवडायचे? (Total Questions Limit)</label>
                <input type="number" name="question_limit" class="form-control" value="50" min="5" max="150" required>
                <small style="color:#777;">सिस्टीम निवडलेल्या धड्यांमधून रँडमली तितके प्रश्न आपोआप निवडेल.</small>
            </div>

            <div class="form-group">
                <label>अभ्यासक्रमाचे धडे निवडा (Select Chapters to Include):</label>
                <div class="chapter-selector">
                    <?php if (empty($chapters)): ?>
                        <p style="color: #888;">कोणतेही धडे उपलब्ध नाहीत.</p>
                    <?php else: ?>
                        <div style="margin-bottom: 8px;">
                            <label><input type="checkbox" id="selectAllCh" onclick="toggleAllChapters(this)"> <strong>सर्व धडे निवडा (Select All)</strong></label>
                        </div>
                        <?php foreach ($chapters as $ch): ?>
                            <div class="chapter-item">
                                <input type="checkbox" name="chapter_ids[]" value="<?php echo $ch['chapter_id']; ?>" class="ch-checkbox">
                                <span><strong>[<?php echo htmlspecialchars($ch['subject_name']); ?>]</strong> <?php echo htmlspecialchars($ch['chapter_name']); ?> (<?php echo $ch['mcq_count']; ?> प्रश्न)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>विद्यार्थ्यांसाठी सूचना (Instructions in Marathi)</label>
                <textarea name="instructions" class="form-control" rows="3" placeholder="उदा. प्रत्येक बरोबर उत्तराला २ गुण मिळतील. निगेटिव्ह मार्किंग नाही. सर्व प्रश्न सोडवणे अनिवार्य आहे. निकाल संध्याकाळी ६ वाजता जाहीर होईल."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" onclick="closeModal()" style="padding: 12px 20px; border-radius: 8px; border: 1px solid #ddd; background: white; cursor: pointer;">रद्द करा</button>
                <button type="submit" class="btn-create">🚀 Live Exam तयार व पब्लिश करा</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('createModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('createModal').style.display = 'none';
}
function toggleAllChapters(source) {
    const checkboxes = document.querySelectorAll('.ch-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}
function shareWhatsApp(text) {
    const encoded = encodeURIComponent(text);
    const url = "https://api.whatsapp.com/send?text=" + encoded;
    window.open(url, '_blank');
}
window.onclick = function(event) {
    const modal = document.getElementById('createModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>
