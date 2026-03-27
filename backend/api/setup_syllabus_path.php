<?php
/**
 * Setup Syllabus Path API
 * Generates a day-by-day roadmap where every 2 chapters are followed
 * by a dedicated "Mega Revision Blitz" day that tests ONLY those 2 chapters.
 * The final 3 days always get a full-syllabus Final Mega Blitz.
 *
 * Requires study_tasks.chapter_ids TEXT column (migration below):
 *   ALTER TABLE study_tasks ADD COLUMN IF NOT EXISTS chapter_ids TEXT DEFAULT NULL;
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    if (isset($_POST['user_id'])) {
        $input = $_POST;
    } else {
        $input = json_decode($_POST['data'] ?? '{}', true);
    }
}

if (!isset($input['user_id']) || !isset($input['exam_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

$user_id    = intval($input['user_id']);
$exam_date  = $input['exam_date'];
$subject_ids  = isset($input['subject_ids'])  ? $input['subject_ids']  : [];
$chapter_ids  = isset($input['chapter_ids'])  ? $input['chapter_ids']  : [];

    // Logic starts directly with validation and data preparation
    // No transaction until the very end to prevent lock-death.

// ----------------------------------------------------------------
// 4. Validate chapter selection
// ----------------------------------------------------------------
if (empty($chapter_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No chapters selected.']);
    exit();
}

$ch_ids_string = implode(',', array_map('intval', $chapter_ids));
$sql = "SELECT ch.chapter_id, ch.chapter_name, s.subject_name
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ch.chapter_id IN ($ch_ids_string)
        ORDER BY ch.subject_id, ch.chapter_order ASC";
$chapters = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($chapters)) {
    echo json_encode(['status' => 'error', 'message' => 'Chapters not found in database.']);
    exit();
}

$total_chapters = count($chapters);

// ----------------------------------------------------------------
// 5. Calculate available study days (leave 3 for final blitz)
// ----------------------------------------------------------------
$start      = new DateTime();
$end        = new DateTime($exam_date);
$total_days = max(1, $end->diff($start)->days);
$safe_days  = max(1, $total_days - 3);

$day_offset          = 0;
$current_chapter_idx = 0;
$batch_chapter_ids   = [];
$tasksToInsert       = [];

while ($current_chapter_idx < $total_chapters) {
    if ($day_offset >= $safe_days) break;

    $ch   = $chapters[$current_chapter_idx];
    $date = date('Y-m-d', strtotime("+{$day_offset} days"));

    $tasksToInsert[] = [$user_id, 0, $date, $ch['subject_name'], $ch['chapter_id'], null, "Watch: " . $ch['chapter_name'], 'video', 20, 50];
    $tasksToInsert[] = [$user_id, 0, $date, $ch['subject_name'], $ch['chapter_id'], null, "Read Notes: " . $ch['chapter_name'], 'notes', 15, 40];
    $tasksToInsert[] = [$user_id, 0, $date, $ch['subject_name'], $ch['chapter_id'], null, "Cards: " . $ch['chapter_name'], 'flashcard', 10, 60];
    $tasksToInsert[] = [$user_id, 0, $date, $ch['subject_name'], $ch['chapter_id'], null, "Quiz: " . $ch['chapter_name'], 'quiz', 15, 100];

    $batch_chapter_ids[] = $ch['chapter_id'];
    $current_chapter_idx++;
    $day_offset++;

    if (count($batch_chapter_ids) === 2 || $current_chapter_idx === $total_chapters) {
        if (count($batch_chapter_ids) >= 1 && $day_offset < $safe_days) {
            $blitz_date     = date('Y-m-d', strtotime("+{$day_offset} days"));
            $blitz_ch_ids   = json_encode($batch_chapter_ids);
            
            $blitz_label_parts = [];
            foreach ($batch_chapter_ids as $bid) {
                foreach ($chapters as $c) {
                    if ($c['chapter_id'] == $bid) { $blitz_label_parts[] = $c['chapter_name']; break; }
                }
            }
            $blitz_title = 'Mega Revision Blitz: ' . implode(' + ', $blitz_label_parts);
            $tasksToInsert[] = [$user_id, 0, $blitz_date, 'Revision', null, $blitz_ch_ids, $blitz_title, 'mega', 60, 500];
            $day_offset++;
            $batch_chapter_ids = [];
        }
    }
}

$all_chapter_ids_json = json_encode(array_map(fn($c) => $c['chapter_id'], $chapters));
for ($day = $safe_days; $day < $total_days; $day++) {
    $date = date('Y-m-d', strtotime("+{$day} days"));
    $tasksToInsert[] = [$user_id, 0, $date, 'Full Syllabus', null, $all_chapter_ids_json, 'Final Mega Blitz', 'mega', 120, 500];
}

// ----------------------------------------------------------------
// START TRANSACTION (Only for Mutations)
// ----------------------------------------------------------------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO study_plans (user_id, focus_subjects, goal_type, target_date, target_hours_per_day)
         VALUES (?, ?, 'monthly_goal', ?, 2.0)
         ON DUPLICATE KEY UPDATE 
            focus_subjects = VALUES(focus_subjects), 
            target_date = VALUES(target_date), updated_at = NOW()"
    );
    $stmt->execute([$user_id, json_encode($subject_ids), $exam_date]);

    $plan_id = $pdo->lastInsertId();
    if (!$plan_id) {
        $s = $pdo->prepare("SELECT plan_id FROM study_plans WHERE user_id = ?");
        $s->execute([$user_id]);
        $plan_id = $s->fetchColumn();
    }

    // Clear old data
    $pdo->prepare("DELETE FROM study_tasks WHERE user_id = ? AND status = 'pending'")->execute([$user_id]);

    // Bulk insert with correct plan_id
    if (!empty($tasksToInsert)) {
        $chunks = array_chunk($tasksToInsert, 50);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
            $flatData = [];
            foreach ($chunk as $row) {
                $row[1] = $plan_id; // Set actual plan_id
                foreach ($row as $val) $flatData[] = $val;
            }
            $stmt = $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, chapter_ids, title, task_type, duration_minutes, xp_reward) VALUES $placeholders");
            $stmt->execute($flatData);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Custom chapter path generated successfully']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $rbEx) {}
    }
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}
?>
