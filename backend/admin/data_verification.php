<?php
/**
 * Data Verification Center - Veeru Admin Panel (backend/admin)
 * On-Demand Verification of MCQs, Flashcards, and Quick Revisions
 * Uses PHP & MySQL Exact and Fuzzy String Matching Algorithms
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

require_once '../config/db.php';

$message = '';
$messageType = '';

// -------------------------------------------------------------
// HANDLE SINGLE DELETE ACTION
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_single') {
    $item_type = $_GET['type'] ?? '';
    $item_id = intval($_GET['id'] ?? 0);

    if ($item_id > 0) {
        try {
            if ($item_type === 'mcq') {
                $stmt = $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?");
                $stmt->execute([$item_id]);
                $message = "MCQ #{$item_id} deleted successfully.";
            } elseif ($item_type === 'flashcard') {
                $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
                $stmt->execute([$item_id]);
                $message = "Flashcard #{$item_id} deleted successfully.";
            } elseif ($item_type === 'quick_revision') {
                $stmt = $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?");
                $stmt->execute([$item_id]);
                $message = "Quick Revision #{$item_id} deleted successfully.";
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Deletion failed: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// -------------------------------------------------------------
// HANDLE BULK DELETE ACTION
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $selected_items = $_POST['selected_items'] ?? [];
    $deleted_count = 0;

    if (!empty($selected_items)) {
        foreach ($selected_items as $item_str) {
            $parts = explode('_', $item_str, 2);
            if (count($parts) === 2) {
                $type = $parts[0];
                $id = intval($parts[1]);

                if ($type === 'mcq') {
                    $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?")->execute([$id]);
                    $deleted_count++;
                } elseif ($type === 'flashcard') {
                    $pdo->prepare("DELETE FROM flashcards WHERE id = ?")->execute([$id]);
                    $deleted_count++;
                } elseif ($type === 'quickrevision' || $type === 'quick_revision') {
                    $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?")->execute([$id]);
                    $deleted_count++;
                }
            }
        }
        $message = "Bulk Delete completed! Removed {$deleted_count} item(s).";
        $messageType = 'success';
    } else {
        $message = "No items selected for deletion.";
        $messageType = 'error';
    }
}

// -------------------------------------------------------------
// FETCH CLASSES & SUBJECTS FOR DROPDOWNS
// -------------------------------------------------------------
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_name ASC");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'all';

$subjects = [];
if ($selected_class_id > 0) {
    $stmtSub = $pdo->prepare("SELECT * FROM subjects WHERE class_id = ? ORDER BY subject_name ASC");
    $stmtSub->execute([$selected_class_id]);
    $subjects = $stmtSub->fetchAll();
}

// -------------------------------------------------------------
// ON-DEMAND VERIFICATION ENGINE
// -------------------------------------------------------------
$verification_results = [
    'total_analyzed' => 0,
    'duplicates_count' => 0,
    'incorrect_count' => 0,
    'irrelevant_count' => 0,
    'items' => []
];

$has_run = isset($_GET['run']) && $_GET['run'] == '1';

if ($has_run && $selected_class_id > 0 && $selected_subject_id > 0) {

    function normalizeText($str) {
        $str = mb_strtolower(trim(strip_tags($str)), 'UTF-8');
        $str = preg_replace('/[^\w\s\d]/u', '', $str);
        return preg_replace('/\s+/', ' ', $str);
    }

    $flagged_items = [];
    $total_analyzed = 0;

    // 1. VERIFY MCQs
    if ($selected_content_type === 'all' || $selected_content_type === 'mcq') {
        $stmtMCQs = $pdo->prepare("
            SELECT m.*, ch.chapter_name, s.subject_name, c.class_name
            FROM mcqs m
            JOIN chapters ch ON m.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY m.mcq_id ASC
        ");
        $stmtMCQs->execute([$selected_subject_id, $selected_board]);
        $mcq_list = $stmtMCQs->fetchAll();
        $total_analyzed += count($mcq_list);

        // Fetch cross-subject MCQs for relevance / cross-subject check
        $stmtOtherMCQs = $pdo->prepare("
            SELECT m.mcq_id, m.question, s.subject_name
            FROM mcqs m
            JOIN chapters ch ON m.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE s.subject_id != ?
        ");
        $stmtOtherMCQs->execute([$selected_subject_id]);
        $other_mcqs = $stmtOtherMCQs->fetchAll();

        $seen_mcq_texts = [];

        foreach ($mcq_list as $mcq) {
            $mcq_id = $mcq['mcq_id'];
            $q_raw = $mcq['question'] ?? '';
            $q_norm = normalizeText($q_raw);
            $opt_a = trim($mcq['option_a'] ?? '');
            $opt_b = trim($mcq['option_b'] ?? '');
            $opt_c = trim($mcq['option_c'] ?? '');
            $opt_d = trim($mcq['option_d'] ?? '');
            $correct = strtolower(trim($mcq['correct_answer'] ?? ''));

            $issues = [];

            // A. Incorrect / Malformed Data Check
            if (empty($q_norm) || strlen($q_norm) < 3) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Empty or extremely short question text.'];
            }
            if ($opt_a === '' || $opt_b === '') {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Missing basic options (Option A or B is blank).'];
            }
            if (!in_array($correct, ['a', 'b', 'c', 'd', 'option_a', 'option_b', 'option_c', 'option_d'])) {
                $issues[] = ['type' => 'incorrect', 'reason' => "Invalid correct_answer key: '{$mcq['correct_answer']}'. Must be a, b, c, or d."];
            } else {
                $correct_letter = str_replace('option_', '', $correct);
                $target_option_val = $mcq["option_" . $correct_letter] ?? '';
                if (empty(trim($target_option_val))) {
                    $issues[] = ['type' => 'incorrect', 'reason' => "Correct answer option '(" . strtoupper($correct_letter) . ")' is empty!"];
                }
            }
            $options_array = array_filter([$opt_a, $opt_b, $opt_c, $opt_d]);
            if (count($options_array) !== count(array_unique($options_array))) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Duplicate option choices found within this MCQ.'];
            }

            // B. Duplicate Check (Exact & Fuzzy)
            if (!empty($q_norm)) {
                if (isset($seen_mcq_texts[$q_norm])) {
                    $orig_id = $seen_mcq_texts[$q_norm];
                    $issues[] = ['type' => 'duplicate', 'reason' => "Exact Duplicate of MCQ #{$orig_id} in this subject."];
                } else {
                    foreach ($seen_mcq_texts as $seen_text => $orig_id) {
                        similar_text($q_norm, $seen_text, $percent);
                        if ($percent >= 95.0) {
                            $issues[] = ['type' => 'duplicate', 'reason' => "Near Duplicate of MCQ #{$orig_id} (" . round($percent, 1) . "% match, 95%+ threshold)."];
                            break;
                        }
                    }
                    if (empty($issues)) {
                        $seen_mcq_texts[$q_norm] = $mcq_id;
                    }
                }
            }

            // C. Irrelevant / Cross-Subject Check
            if (!empty($q_norm)) {
                foreach ($other_mcqs as $other) {
                    $other_norm = normalizeText($other['question']);
                    if ($q_norm === $other_norm) {
                        $issues[] = ['type' => 'irrelevant', 'reason' => "Cross-Subject Duplicate: Matches MCQ #{$other['mcq_id']} in '{$other['subject_name']}'."];
                        break;
                    }
                }
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'MCQ',
                        'item_key' => 'mcq_' . $mcq_id,
                        'db_type' => 'mcq',
                        'id' => $mcq_id,
                        'preview' => $mcq['question'],
                        'details' => "Options: (A) {$opt_a} | (B) {$opt_b} | Correct: " . strtoupper($correct),
                        'chapter' => $mcq['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    // 2. VERIFY FLASHCARDS
    if ($selected_content_type === 'all' || $selected_content_type === 'flashcard') {
        $stmtFC = $pdo->prepare("
            SELECT f.*, ch.chapter_name, s.subject_name, c.class_name
            FROM flashcards f
            JOIN chapters ch ON f.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY f.id ASC
        ");
        $stmtFC->execute([$selected_subject_id, $selected_board]);
        $fc_list = $stmtFC->fetchAll();
        $total_analyzed += count($fc_list);

        $seen_fc_texts = [];

        foreach ($fc_list as $fc) {
            $fc_id = $fc['id'];
            $front = trim($fc['question_front'] ?? '');
            $back = trim($fc['answer_back'] ?? '');
            $front_norm = normalizeText($front);
            $back_norm = normalizeText($back);

            $issues = [];

            if (empty($front_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Question Front is empty.'];
            }
            if (empty($back_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Answer Back is empty.'];
            }
            if (!empty($front_norm) && $front_norm === $back_norm) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Question Front and Answer Back are identical.'];
            }

            if (!empty($front_norm)) {
                if (isset($seen_fc_texts[$front_norm])) {
                    $orig_id = $seen_fc_texts[$front_norm];
                    $issues[] = ['type' => 'duplicate', 'reason' => "Exact Duplicate of Flashcard #{$orig_id}."];
                } else {
                    foreach ($seen_fc_texts as $seen_text => $orig_id) {
                        similar_text($front_norm, $seen_text, $percent);
                        if ($percent >= 95.0) {
                            $issues[] = ['type' => 'duplicate', 'reason' => "Near Duplicate of Flashcard #{$orig_id} (" . round($percent, 1) . "% match, 95%+ threshold)."];
                            break;
                        }
                    }
                    if (empty($issues)) {
                        $seen_fc_texts[$front_norm] = $fc_id;
                    }
                }
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'Flashcard',
                        'item_key' => 'flashcard_' . $fc_id,
                        'db_type' => 'flashcard',
                        'id' => $fc_id,
                        'preview' => "Front: " . $front,
                        'details' => "Back: " . $back,
                        'chapter' => $fc['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    // 3. VERIFY QUICK REVISIONS
    if ($selected_content_type === 'all' || $selected_content_type === 'quick_revision') {
        $stmtQR = $pdo->prepare("
            SELECT qr.*, ch.chapter_name, s.subject_name, c.class_name
            FROM quick_revision qr
            JOIN chapters ch ON qr.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY qr.revision_id ASC
        ");
        $stmtQR->execute([$selected_subject_id, $selected_board]);
        $qr_list = $stmtQR->fetchAll();
        $total_analyzed += count($qr_list);

        $seen_qr_titles = [];

        foreach ($qr_list as $qr) {
            $qr_id = $qr['revision_id'];
            $title = trim($qr['title'] ?? '');
            $summary = trim($qr['summary'] ?? '');
            $title_norm = normalizeText($title);

            $issues = [];

            if (empty($title_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Quick Revision title is empty.'];
            }
            if (empty(trim($summary)) && empty($qr['key_points'])) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Summary content and key points are both empty.'];
            }

            if (!empty($title_norm)) {
                if (isset($seen_qr_titles[$title_norm])) {
                    $orig_id = $seen_qr_titles[$title_norm];
                    $issues[] = ['type' => 'duplicate', 'reason' => "Exact Duplicate Title of Quick Revision #{$orig_id}."];
                } else {
                    $seen_qr_titles[$title_norm] = $qr_id;
                }
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'Quick Revision',
                        'item_key' => 'quickrevision_' . $qr_id,
                        'db_type' => 'quick_revision',
                        'id' => $qr_id,
                        'preview' => "Title: " . $title,
                        'details' => "Summary: " . (strlen($summary) > 100 ? substr($summary, 0, 100) . '...' : $summary),
                        'chapter' => $qr['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    $verification_results['total_analyzed'] = $total_analyzed;
    $verification_results['items'] = $flagged_items;

    foreach ($flagged_items as $item) {
        if ($item['flag_category'] === 'duplicate') $verification_results['duplicates_count']++;
        elseif ($item['flag_category'] === 'incorrect') $verification_results['incorrect_count']++;
        elseif ($item['flag_category'] === 'irrelevant') $verification_results['irrelevant_count']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Verification Center - MCQ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <style>
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 14px; font-weight: 600; color: #555; }
        select, input[type="text"] { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; outline: none; background: white; }
        .btn-verify { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-verify:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 5px solid #ccc; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.orange { border-left-color: #f97316; }
        .stat-card.yellow { border-left-color: #eab308; }
        .stat-card .label { font-size: 13px; color: #666; font-weight: 500; margin-bottom: 5px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #111; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 50px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-incorrect { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .badge-irrelevant { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .badge-duplicate { background: #fef9c3; color: #ca8a04; border: 1px solid #fef08a; }
        .badge-type { background: #e0e7ff; color: #4338ca; font-size: 11px; padding: 3px 8px; border-radius: 4px; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; vertical-align: top; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        tr:hover { background: #f8fafc; }
        .btn-delete-single { color: #dc2626; text-decoration: none; font-weight: 600; padding: 6px 12px; background: #fee2e2; border-radius: 6px; font-size: 12px; transition: background 0.2s; }
        .btn-delete-single:hover { background: #fca5a5; }
        .btn-bulk-delete { background: #dc2626; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-bulk-delete:hover { background: #b91c1c; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header glass">
        <h1>🎓 Veeru Admin</h1>
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">🔁 Switch Board</a>
            <span>Running: <?php echo htmlspecialchars($board_name); ?></span>
        </div>
        <div class="header-right">
            <div class="admin-info">
                <div class="name">
                    <span><?php echo htmlspecialchars($board_name); ?></span>
                    &nbsp; <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </div>
                <div class="email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="data_verification.php" class="active"><i class="fa-solid fa-shield-halved"></i> Verify Data</a></li>
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>

    <div class="container">

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Selection Card -->
        <div class="card">
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 20px;">
                <i class="fa-solid fa-shield-halved"></i> Select Class & Subject to Run Audit
            </h2>
            <form method="GET" action="data_verification.php">
                <input type="hidden" name="run" value="1">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="class_id">Target Class</label>
                        <select name="class_id" id="class_id" required onchange="this.form.submit()">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>" <?php echo ($selected_class_id == $c['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_id">Target Subject</label>
                        <select name="subject_id" id="subject_id" required <?php echo empty($subjects) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['subject_id']; ?>" <?php echo ($selected_subject_id == $s['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="content_type">Content Type</label>
                        <select name="content_type" id="content_type">
                            <option value="all" <?php echo ($selected_content_type === 'all') ? 'selected' : ''; ?>>All Content Types</option>
                            <option value="mcq" <?php echo ($selected_content_type === 'mcq') ? 'selected' : ''; ?>>MCQs Only</option>
                            <option value="flashcard" <?php echo ($selected_content_type === 'flashcard') ? 'selected' : ''; ?>>Flashcards Only</option>
                            <option value="quick_revision" <?php echo ($selected_content_type === 'quick_revision') ? 'selected' : ''; ?>>Quick Revisions Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-verify"><i class="fa-solid fa-bolt"></i> Run Verification Check</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($has_run): ?>
            <!-- Statistics Summary -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="label">Total Items Analyzed</div>
                    <div class="value"><?php echo $verification_results['total_analyzed']; ?></div>
                </div>
                <div class="stat-card red">
                    <div class="label">🔴 Incorrect Data</div>
                    <div class="value"><?php echo $verification_results['incorrect_count']; ?></div>
                </div>
                <div class="stat-card orange">
                    <div class="label">🟠 Irrelevant / Misassigned</div>
                    <div class="value"><?php echo $verification_results['irrelevant_count']; ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="label">🟡 Duplicate Data</div>
                    <div class="value"><?php echo $verification_results['duplicates_count']; ?></div>
                </div>
            </div>

            <!-- Flagged Items List -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="font-size: 18px; font-weight: 700;">Audit Results & Issue Flags</h2>
                    <?php if (!empty($verification_results['items'])): ?>
                        <span style="font-size: 14px; font-weight: 500; color: #64748b;">
                            Found <strong><?php echo count($verification_results['items']); ?></strong> flagged item(s)
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($verification_results['items'])): ?>
                    <div style="text-align: center; padding: 40px; color: #16a34a; font-weight: 600; font-size: 16px;">
                        🎉 Excellent! No duplicates, incorrect options, or irrelevant items found in this Subject!
                    </div>
                <?php else: ?>

                    <!-- Bulk Actions Form -->
                    <form method="POST" action="data_verification.php?run=1&class_id=<?php echo $selected_class_id; ?>&subject_id=<?php echo $selected_subject_id; ?>&content_type=<?php echo $selected_content_type; ?>" onsubmit="return confirm('Are you sure you want to delete all selected flagged items?');">
                        <input type="hidden" name="action" value="bulk_delete">
                        
                        <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                <label for="selectAll" style="font-weight: 600; font-size: 14px; cursor: pointer; margin-left: 5px;">Select All Flagged Items</label>
                            </div>
                            <button type="submit" class="btn-bulk-delete"><i class="fa-solid fa-trash-can"></i> Delete Selected Items</button>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Select</th>
                                    <th style="width: 110px;">Type</th>
                                    <th>Content Preview</th>
                                    <th>Issue Category & Reason</th>
                                    <th style="width: 140px;">Chapter</th>
                                    <th style="width: 90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verification_results['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['item_key']; ?>" class="item-checkbox">
                                        </td>
                                        <td>
                                            <span class="badge-type"><?php echo htmlspecialchars($item['type']); ?></span>
                                            <br><small style="color: #94a3b8;">#<?php echo $item['id']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['preview']); ?></strong>
                                            <br><small style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($item['details']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($item['flag_category'] === 'incorrect'): ?>
                                                <span class="badge badge-incorrect">🔴 Incorrect Data</span>
                                            <?php elseif ($item['flag_category'] === 'irrelevant'): ?>
                                                <span class="badge badge-irrelevant">🟠 Irrelevant</span>
                                            <?php elseif ($item['flag_category'] === 'duplicate'): ?>
                                                <span class="badge badge-duplicate">🟡 Duplicate</span>
                                            <?php endif; ?>
                                            <div style="margin-top: 5px; font-size: 13px; color: #334155;">
                                                <?php echo htmlspecialchars($item['reason']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($item['chapter']); ?></small>
                                        </td>
                                        <td>
                                            <a href="data_verification.php?run=1&class_id=<?php echo $selected_class_id; ?>&subject_id=<?php echo $selected_subject_id; ?>&content_type=<?php echo $selected_content_type; ?>&action=delete_single&type=<?php echo $item['db_type']; ?>&id=<?php echo $item['id']; ?>" 
                                               class="btn-delete-single"
                                               onclick="return confirm('Delete this <?php echo $item['type']; ?> permanently?');">
                                               Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>

                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
        }
    </script>
</body>
</html>
