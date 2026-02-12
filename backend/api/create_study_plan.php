<?php
/**
 * Create Study Plan API (Syllabus Integrated & Precise Estimation)
 * Generates tasks based on actual chapter content with specific time multipliers
 * FIXED: User ID integrity check added
 */

header('Content-Type: application/json');
require_once '../config/db.php';

// Enable error reporting for debugging (but return JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendError($msg, $details = null, $code = 500) {
    if (ob_get_length()) ob_clean(); 
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg, 'details' => $details]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user_id']) || !isset($input['focus_subjects'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }

    $user_id = intval($input['user_id']);
    
    // Check if user exists
    $stmt_user = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    if (!$stmt_user->fetch()) {
        sendError("User record not found. Please Logout and Login again.", null, 404);
    }
    
    // Validate Subjects
    $raw_subjects = is_array($input['focus_subjects']) ? $input['focus_subjects'] : [];
    $focus_subjects = array_filter(array_map('intval', array_values($raw_subjects)));
    
    if (empty($focus_subjects)) {
        throw new Exception("No valid subjects selected.");
    }

    $target_hours = isset($input['target_hours']) ? floatval($input['target_hours']) : 2.0;
    
    // New Fields
    $goal_type = isset($input['goal_type']) ? $input['goal_type'] : 'daily_habit'; 
    $target_date = !empty($input['target_date']) ? $input['target_date'] : null;

    $pdo->beginTransaction();

    // 1. Create/Update Study Plan Record
    try {
        // FIX: Using unique parameter names for UPDATE clause to avoid HY093
        $stmt = $pdo->prepare("INSERT INTO study_plans (user_id, target_hours_per_day, focus_subjects, goal_type, target_date) 
                              VALUES (:uid, :hours, :subjects, :gtype, :tdate)
                              ON DUPLICATE KEY UPDATE 
                              target_hours_per_day = :hours_u, focus_subjects = :subjects_u, 
                              goal_type = :gtype_u, target_date = :tdate_u, updated_at = NOW()");
        
        $subjects_json = json_encode($focus_subjects);
        $stmt->execute([
            ':uid' => $user_id,
            ':hours' => $target_hours,
            ':subjects' => $subjects_json,
            ':gtype' => $goal_type,
            ':tdate' => $target_date,
            // Update params
            ':hours_u' => $target_hours,
            ':subjects_u' => $subjects_json,
            ':gtype_u' => $goal_type,
            ':tdate_u' => $target_date
        ]);
        
        $plan_id = $pdo->lastInsertId();
        if ($plan_id == 0) {
            $stmt = $pdo->prepare("SELECT plan_id FROM study_plans WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $plan_id = $stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        throw new Exception("Step 1 (Plan Record) Failed: " . $e->getMessage());
    }

    // 2. Clear pending tasks
    $pdo->prepare("DELETE FROM study_tasks WHERE user_id = ? AND task_date >= CURRENT_DATE AND status = 'pending'")->execute([$user_id]);

    // 3. Fetch Syllabus with Content Counts (MCQ, Video, Notes, Flashcards)
    $chapter_id_filter = isset($input['chapter_id']) ? intval($input['chapter_id']) : 0;
    
    // DEBUG: Log what we received
    error_log("CREATE_STUDY_PLAN DEBUG - chapter_id_filter: " . $chapter_id_filter);
    error_log("CREATE_STUDY_PLAN DEBUG - focus_subjects: " . json_encode($focus_subjects));
    
    // Build WHERE clause based on whether chapter_id is provided
    if ($chapter_id_filter > 0) {
        // Specific chapter selected - filter by chapter_id only
        $where_clause = "ch.chapter_id = ?";
        $params = [$chapter_id_filter];
    } else {
        // No specific chapter - filter by subject_ids
        $ids_string = implode(',', $focus_subjects);
        $where_clause = "ch.subject_id IN ($ids_string)";
        $params = [];
    }

    try {
        $sql = "SELECT 
                    ch.chapter_id, 
                    ch.chapter_name, 
                    s.subject_name,
                    (SELECT COUNT(*) FROM videos WHERE chapter_id = ch.chapter_id) as total_videos,
                    (SELECT COUNT(*) FROM notes WHERE chapter_id = ch.chapter_id) as total_notes,
                    (SELECT COUNT(*) FROM mcqs WHERE chapter_id = ch.chapter_id) as total_mcqs,
                    (SELECT COUNT(*) FROM flashcards WHERE chapter_id = ch.chapter_id) as total_flashcards
                FROM chapters ch
                JOIN subjects s ON ch.subject_id = s.subject_id
                WHERE $where_clause
                ORDER BY ch.chapter_order ASC";
        
        $stmt_ch = $pdo->prepare($sql);
        $stmt_ch->execute($params); 
        $all_chapters = $stmt_ch->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Step 3 (Fetch Syllabus) Failed: " . $e->getMessage());
    }

    // 4. Generate Detailed Tasks
    $tasks = [];
    $days_to_schedule = 7;
    
    // If specific Chapter is selected, we ONLY schedule for TODAY (1 day)
    if ($chapter_id_filter > 0) {
        $days_to_schedule = 1;
    } elseif ($goal_type === 'monthly_goal' && $target_date) {
        try {
            $start = new DateTime();
            $end = new DateTime($target_date);
            $days_to_schedule = max(1, $end->diff($start)->days);
        } catch (Exception $e) {}
    }
    $days_to_schedule = min($days_to_schedule, 60); 

    $daily_minutes_limit = $target_hours * 60;
    
    // Fallback
    if (count($all_chapters) == 0) {
        for ($i = 0; $i < $days_to_schedule; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $tasks[] = [
                'date' => $date, 'subject' => 'General', 'chapter_id' => null,
                'title' => "Self Study Session", 'type' => 'custom', 'dur' => 60, 'xp' => 100
            ];
        }
    } else {
        $chapter_queue = $all_chapters;
        
        for ($i = 0; $i < $days_to_schedule; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $minutes_scheduled_today = 0;

            // Warm-up (Skip if specific chapter focused)
            if ($chapter_id_filter == 0) {
                $tasks[] = [
                    'date' => $date, 'subject' => 'General', 'chapter_id' => null,
                    'title' => "Quick Revision", 'type' => 'revision', 'dur' => 10, 'xp' => 20
                ];
                $minutes_scheduled_today += 10;
            }

            // If specific chapter is selected, only process that ONE chapter
            if ($chapter_id_filter > 0 && !empty($chapter_queue)) {
                $ch = $chapter_queue[0]; // Only use the selected chapter
                
                // Build all available content types for this chapter
                $content_types = [];
                
                // 1. MCQs
                if ($ch['total_mcqs'] > 0) {
                    $dur = round(($ch['total_mcqs'] * 25) / 60); 
                    $dur = max(5, $dur);
                    $content_types[] = ['title' => "Pract: " . $ch['chapter_name'] . " (" . $ch['total_mcqs'] . " MCQs)", 'type' => 'quiz', 'dur' => $dur, 'xp' => 10 * $ch['total_mcqs']];
                }
                
                // 2. Flashcards
                if ($ch['total_flashcards'] > 0) {
                    $dur = round(($ch['total_flashcards'] * 20) / 60);
                    $dur = max(5, $dur);
                    $content_types[] = ['title' => "Flashcards: " . $ch['chapter_name'] . " (" . $ch['total_flashcards'] . " Cards)", 'type' => 'quiz', 'dur' => $dur, 'xp' => 5 * $ch['total_flashcards']];
                }
                
                // 3. Videos
                if ($ch['total_videos'] > 0) {
                    $dur = 10 * $ch['total_videos'];
                    $content_types[] = ['title' => "Watch: " . $ch['chapter_name'], 'type' => 'video', 'dur' => $dur, 'xp' => 50];
                } else {
                    $content_types[] = ['title' => "Read: " . $ch['chapter_name'], 'type' => 'video', 'dur' => 15, 'xp' => 30];
                }
                
                // 4. Notes
                if ($ch['total_notes'] > 0) {
                    $content_types[] = ['title' => "Notes: " . $ch['chapter_name'], 'type' => 'revision', 'dur' => 10, 'xp' => 30];
                }
                
                // Fill the time by cycling through available content types
                $content_index = 0;
                while ($minutes_scheduled_today < $daily_minutes_limit && !empty($content_types)) {
                    $st = $content_types[$content_index % count($content_types)];
                    
                    $tasks[] = [
                        'date' => $date, 
                        'subject' => $ch['subject_name'], 
                        'chapter_id' => $ch['chapter_id'],
                        'title' => $st['title'], 
                        'type' => $st['type'], 
                        'dur' => $st['dur'], 
                        'xp' => $st['xp']
                    ];
                    $minutes_scheduled_today += $st['dur'];
                    $content_index++;
                    
                    // Safety: prevent infinite loop
                    if ($content_index > 20) break;
                }
            } else {
                // Original multi-chapter logic for regular plans
                while ($minutes_scheduled_today < $daily_minutes_limit && !empty($chapter_queue)) {
                    $ch = $chapter_queue[0]; 
                    $sub_tasks = [];
                    
                    // 1. MCQs: 25 seconds per MCQ
                    // e.g., 40 MCQs -> 40 * 25 / 60 = 16.6 mins => rounded to 17 mins
                if ($ch['total_mcqs'] > 0) {
                    $dur = round(($ch['total_mcqs'] * 25) / 60); 
                    $dur = max(5, $dur); // Minimum 5 mins if very few
                    $sub_tasks[] = ['title' => "Pract: " . $ch['chapter_name'] . " (" . $ch['total_mcqs'] . " MCQs)", 'type' => 'quiz', 'dur' => $dur, 'xp' => 10 * $ch['total_mcqs']];
                }

                // 2. Flashcards: 20 seconds per Flashcard
                // e.g., 40 FC -> 40 * 20 / 60 = 13.3 mins => rounded to 13 mins
                if ($ch['total_flashcards'] > 0) {
                    $dur = round(($ch['total_flashcards'] * 20) / 60);
                    $dur = max(5, $dur);
                    // Use 'revision' type for Flashcards for now, or 'quiz'
                    $sub_tasks[] = ['title' => "Flashcards: " . $ch['chapter_name'] . " (" . $ch['total_flashcards'] . " Cards)", 'type' => 'quiz', 'dur' => $dur, 'xp' => 5 * $ch['total_flashcards']];
                }

                // 3. Videos: 10 mins per Video
                if ($ch['total_videos'] > 0) {
                    $dur = 10 * $ch['total_videos'];
                    $sub_tasks[] = ['title' => "Watch: " . $ch['chapter_name'], 'type' => 'video', 'dur' => $dur, 'xp' => 50];
                } else {
                     // Fallback Reading if no video: 15 mins
                    $sub_tasks[] = ['title' => "Read: " . $ch['chapter_name'], 'type' => 'video', 'dur' => 15, 'xp' => 30];
                }

                // 4. Notes: 10 mins flat per chapter
                if ($ch['total_notes'] > 0) {
                    $sub_tasks[] = ['title' => "Notes: " . $ch['chapter_name'], 'type' => 'revision', 'dur' => 10, 'xp' => 30];
                }

                // Add to Day
                foreach ($sub_tasks as $st) {
                    if ($minutes_scheduled_today >= $daily_minutes_limit) break; 
                    
                    $tasks[] = [
                        'date' => $date, 
                        'subject' => $ch['subject_name'], 
                        'chapter_id' => $ch['chapter_id'],
                        'title' => $st['title'], 
                        'type' => $st['type'], 
                        'dur' => $st['dur'], 
                        'xp' => $st['xp']
                    ];
                    $minutes_scheduled_today += $st['dur'];
                }

                array_shift($chapter_queue);
                }
            }
        }
    }

    // Insert Tasks
    try {
        $sql_insert = "INSERT INTO study_tasks (user_id, plan_id, task_date, subject, chapter_id, title, task_type, duration_minutes, xp_reward) VALUES (:uid, :pid, :date, :subj, :chid, :title, :type, :dur, :xp)";
        $stmt_insert = $pdo->prepare($sql_insert);
        
        foreach ($tasks as $task) {
            $stmt_insert->execute([
                ':uid' => $user_id,
                ':pid' => $plan_id,
                ':date' => $task['date'],
                ':subj' => $task['subject'],
                ':chid' => $task['chapter_id'],
                ':title' => $task['title'],
                ':type' => $task['type'],
                ':dur' => $task['dur'],
                ':xp' => $task['xp']
            ]);
        }
    } catch (PDOException $e) {
        throw new Exception("Step 5 (Insert Tasks) Failed: " . $e->getMessage());
    }

    $pdo->prepare("INSERT IGNORE INTO study_streaks (user_id, current_streak, total_xp) VALUES (:uid, 0, 0)")->execute([':uid' => $user_id]);
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Precise Plan created! " . count($tasks) . " tasks scheduled.",
        'plan_id' => $plan_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError($e->getMessage());
}
?>
