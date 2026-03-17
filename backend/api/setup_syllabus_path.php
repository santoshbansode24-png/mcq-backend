<?php
/**
 * Setup Syllabus Path API
 * Wraps the study plan creation logic with focus on Exam Prep
 * Now handles specific chapter selection
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['exam_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

$user_id = intval($input['user_id']);
$exam_date = $input['exam_date'];
$subject_ids = isset($input['subject_ids']) ? $input['subject_ids'] : [];
$chapter_ids = isset($input['chapter_ids']) ? $input['chapter_ids'] : [];

try {
    $pdo->beginTransaction();

    // 1. Update/Create Plan
    $stmt = $pdo->prepare("INSERT INTO study_plans (user_id, focus_subjects, goal_type, target_date, target_hours_per_day) 
                          VALUES (?, ?, 'monthly_goal', ?, 2.0)
                          ON DUPLICATE KEY UPDATE 
                          focus_subjects = VALUES(focus_subjects), 
                          goal_type = 'monthly_goal', 
                          target_date = VALUES(target_date),
                          updated_at = NOW()");
    $stmt->execute([$user_id, json_encode($subject_ids), $exam_date]);

    $plan_id = $pdo->lastInsertId();
    if (!$plan_id) {
        $stmt = $pdo->prepare("SELECT plan_id FROM study_plans WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $plan_id = $stmt->fetchColumn();
    }

    // 2. Clear old pending tasks
    $pdo->prepare("DELETE FROM study_tasks WHERE user_id = ? AND status = 'pending'")->execute([$user_id]);

    // 3. Fetch chapters based on specific selection
    if (empty($chapter_ids)) {
        throw new Exception("No chapters selected.");
    }

    $ch_ids_string = implode(',', array_map('intval', $chapter_ids));
    $sql = "SELECT ch.chapter_id, ch.chapter_name, s.subject_name 
            FROM chapters ch 
            JOIN subjects s ON ch.subject_id = s.subject_id 
            WHERE ch.chapter_id IN ($ch_ids_string) 
            ORDER BY ch.subject_id, ch.chapter_order ASC";
    $chapters = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($chapters)) {
        throw new Exception("Chapters not found in database.");
    }

    // 4. Distribute chapters over days
    $start = new DateTime();
    $end = new DateTime($exam_date);
    $total_days = max(1, $end->diff($start)->days);
    
    // Safety: Finish 3 days early for final push
    $safe_days = max(1, $total_days - 3); 
    
    $chapters_per_day = ceil(count($chapters) / $safe_days);
    
    $current_chapter_idx = 0;
    for ($day = 0; $day < $safe_days; $day++) {
        $date = date('Y-m-d', strtotime("+$day days"));
        
        for ($c = 0; $c < $chapters_per_day; $c++) {
            if ($current_chapter_idx >= count($chapters)) break;
            
            $ch = $chapters[$current_chapter_idx];
            
            // 1. Video Masterclass
            $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, title, task_type, duration_minutes, xp_reward) 
                          VALUES (?, ?, ?, ?, ?, ?, 'video', 20, 50)")
                ->execute([$user_id, $plan_id, $date, $ch['subject_name'], $ch['chapter_id'], "Watch: " . $ch['chapter_name']]);
                
            // 2. Read Notes
            $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, title, task_type, duration_minutes, xp_reward) 
                          VALUES (?, ?, ?, ?, ?, ?, 'notes', 15, 40)")
                ->execute([$user_id, $plan_id, $date, $ch['subject_name'], $ch['chapter_id'], "Read Notes: " . $ch['chapter_name']]);

            // 3. Flashcards (Active Recall)
            $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, title, task_type, duration_minutes, xp_reward) 
                          VALUES (?, ?, ?, ?, ?, ?, 'flashcard', 10, 60)")
                ->execute([$user_id, $plan_id, $date, $ch['subject_name'], $ch['chapter_id'], "Cards: " . $ch['chapter_name']]);
                
            // 4. Practice Quiz
            $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, title, task_type, duration_minutes, xp_reward) 
                          VALUES (?, ?, ?, ?, ?, ?, 'quiz', 15, 100)")
                ->execute([$user_id, $plan_id, $date, $ch['subject_name'], $ch['chapter_id'], "Quiz: " . $ch['chapter_name']]);
            
            $current_chapter_idx++;

            // --- SMART BLITZ INSERTION ---
            // After every 2 chapters, insert a Mega Revision Blitz for that day
            if ($current_chapter_idx % 2 == 0) {
                $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, title, task_type, duration_minutes, xp_reward) 
                              VALUES (?, ?, ?, 'Full Syllabus', 'Mega Revision Blitz', 'mega', 60, 500)")
                    ->execute([$user_id, $plan_id, $date]);
            }
        }
    }

    // 5. Add Final Revision tasks for the last 3 days
    for ($day = $safe_days; $day < $total_days; $day++) {
        $date = date('Y-m-d', strtotime("+$day days"));
        $pdo->prepare("INSERT INTO study_tasks (user_id, plan_id, task_date, subject, title, task_type, duration_minutes, xp_reward) 
                      VALUES (?, ?, ?, 'Full Syllabus', 'Final Mega Blitz', 'mega', 120, 500)")
            ->execute([$user_id, $plan_id, $date]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Custom chapter path generated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
