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

if (!isset($input['user_id']) || !isset($input['exam_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

$user_id    = intval($input['user_id']);
$exam_date  = $input['exam_date'];
$subject_ids  = isset($input['subject_ids'])  ? $input['subject_ids']  : [];
$chapter_ids  = isset($input['chapter_ids'])  ? $input['chapter_ids']  : [];

try {
    $pdo->beginTransaction();

    // ----------------------------------------------------------------
    // 1. Ensure study_tasks has the chapter_ids column (safe migration)
    // ----------------------------------------------------------------
    try {
        $pdo->exec("ALTER TABLE study_tasks ADD COLUMN chapter_ids TEXT DEFAULT NULL");
    } catch (PDOException $colEx) {
        // Column already exists – ignore duplicate column error (1060)
        if ($colEx->getCode() != '42S21' && strpos($colEx->getMessage(), '1060') === false) {
            throw $colEx;
        }
    }

    // ----------------------------------------------------------------
    // 2. Update / Create Study Plan record
    // ----------------------------------------------------------------
    $stmt = $pdo->prepare(
        "INSERT INTO study_plans
            (user_id, focus_subjects, goal_type, target_date, target_hours_per_day)
         VALUES (?, ?, 'monthly_goal', ?, 2.0)
         ON DUPLICATE KEY UPDATE
            focus_subjects     = VALUES(focus_subjects),
            goal_type          = 'monthly_goal',
            target_date        = VALUES(target_date),
            updated_at         = NOW()"
    );
    $stmt->execute([$user_id, json_encode($subject_ids), $exam_date]);

    $plan_id = $pdo->lastInsertId();
    if (!$plan_id) {
        $s = $pdo->prepare("SELECT plan_id FROM study_plans WHERE user_id = ?");
        $s->execute([$user_id]);
        $plan_id = $s->fetchColumn();
    }

    // ----------------------------------------------------------------
    // 3. Clear old pending tasks
    // ----------------------------------------------------------------
    $pdo->prepare("DELETE FROM study_tasks WHERE user_id = ? AND status = 'pending'")->execute([$user_id]);

    // ----------------------------------------------------------------
    // 4. Validate chapter selection
    // ----------------------------------------------------------------
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

    $total_chapters = count($chapters);

    // ----------------------------------------------------------------
    // 5. Calculate available study days (leave 3 for final blitz)
    // ----------------------------------------------------------------
    $start      = new DateTime();
    $end        = new DateTime($exam_date);
    $total_days = max(1, $end->diff($start)->days);
    $safe_days  = max(1, $total_days - 3);

    // ----------------------------------------------------------------
    // 6. Build the schedule
    //
    // Strategy:
    //   - 1 chapter = 1 study day (4 tasks: video, notes, flashcard, quiz)
    //   - After every 2nd chapter → insert a dedicated Blitz Day
    //     (no normal tasks that day, only the Mega Revision Blitz)
    //     The blitz stores the IDs of the preceding 2 chapters.
    //   - If fewer than 2 chapters remain after safe_days, a blitz is
    //     still inserted for whatever chapters were covered.
    //   - Last 3 days → Final Mega Blitz (all chapters).
    // ----------------------------------------------------------------

    $day_offset          = 0; // absolute offset from today
    $current_chapter_idx = 0;
    $batch_chapter_ids   = []; // accumulates IDs for the current 2-chapter batch

    $insertTask = $pdo->prepare(
        "INSERT INTO study_tasks
            (user_id, plan_id, task_date, subject, chapter_id, chapter_ids,
             title, task_type, duration_minutes, xp_reward)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    while ($current_chapter_idx < $total_chapters) {
        // Guard: don't exceed safe_days
        if ($day_offset >= $safe_days) break;

        $ch   = $chapters[$current_chapter_idx];
        $date = date('Y-m-d', strtotime("+{$day_offset} days"));

        // --- Study tasks for this chapter ---
        $insertTask->execute([$user_id, $plan_id, $date, $ch['subject_name'],
            $ch['chapter_id'], null, "Watch: " . $ch['chapter_name'], 'video', 20, 50]);

        $insertTask->execute([$user_id, $plan_id, $date, $ch['subject_name'],
            $ch['chapter_id'], null, "Read Notes: " . $ch['chapter_name'], 'notes', 15, 40]);

        $insertTask->execute([$user_id, $plan_id, $date, $ch['subject_name'],
            $ch['chapter_id'], null, "Cards: " . $ch['chapter_name'], 'flashcard', 10, 60]);

        $insertTask->execute([$user_id, $plan_id, $date, $ch['subject_name'],
            $ch['chapter_id'], null, "Quiz: " . $ch['chapter_name'], 'quiz', 15, 100]);

        // Track this chapter in the current batch
        $batch_chapter_ids[] = $ch['chapter_id'];
        $current_chapter_idx++;
        $day_offset++;

        // --- After every 2 chapters → dedicated Blitz Day ---
        if (count($batch_chapter_ids) === 2 || $current_chapter_idx === $total_chapters) {
            if (count($batch_chapter_ids) >= 1 && $day_offset < $safe_days) {
                $blitz_date     = date('Y-m-d', strtotime("+{$day_offset} days"));
                $blitz_ch_ids   = json_encode($batch_chapter_ids);

                // Build a short label: "Ch1 + Ch2"
                $blitz_label_parts = [];
                foreach ($batch_chapter_ids as $bid) {
                    foreach ($chapters as $c) {
                        if ($c['chapter_id'] == $bid) {
                            $blitz_label_parts[] = $c['chapter_name'];
                            break;
                        }
                    }
                }
                $blitz_title = 'Mega Revision Blitz: ' . implode(' + ', $blitz_label_parts);

                $insertTask->execute([
                    $user_id, $plan_id, $blitz_date,
                    'Revision',     // subject
                    null,           // chapter_id (NULL for blitz)
                    $blitz_ch_ids,  // chapter_ids JSON array
                    $blitz_title,
                    'mega',
                    60,
                    500
                ]);

                $day_offset++;
                $batch_chapter_ids = []; // reset batch
            }
        }
    }

    // ----------------------------------------------------------------
    // 7. Final 3-day Mega Blitz (covers ALL chapters)
    // ----------------------------------------------------------------
    $all_chapter_ids_json = json_encode(array_map(fn($c) => $c['chapter_id'], $chapters));
    for ($day = $safe_days; $day < $total_days; $day++) {
        $date = date('Y-m-d', strtotime("+{$day} days"));
        $insertTask->execute([
            $user_id, $plan_id, $date,
            'Full Syllabus',
            null,
            $all_chapter_ids_json,
            'Final Mega Blitz',
            'mega',
            120,
            500
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Custom chapter path generated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
