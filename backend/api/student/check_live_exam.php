<?php
require_once '../../config/db.php';
require_once '../cors_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests allowed', null, 405);
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($class_id <= 0) {
    sendResponse('error', 'Valid class_id required', null, 400);
}

try {
    // Look for an active exam for this class
    $stmt = $pdo->prepare("
        SELECT id as exam_id, title, chapter_id, duration_minutes, selected_mcq_ids, created_at 
        FROM live_exams 
        WHERE class_id = ? AND status = 'active'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$class_id]);
    $activeExam = $stmt->fetch();

    if ($activeExam) {
        // Calculate if it has expired based on duration
        $createdAt = strtotime($activeExam['created_at']);
        $durationSeconds = $activeExam['duration_minutes'] * 60;
        $expiryTime = $createdAt + $durationSeconds;
        $currentTime = time();

        if ($currentTime > $expiryTime) {
            // Auto-close it
            $closeStmt = $pdo->prepare("UPDATE live_exams SET status = 'completed' WHERE id = ?");
            $closeStmt->execute([$activeExam['exam_id']]);
            sendResponse('success', 'No active exams', null, 200);
        } else {
            // Check if this student already completed it
            if ($user_id > 0) {
                try {
                    $checkSubmit = $pdo->prepare("SELECT COUNT(*) FROM class_exam_results WHERE update_id = ? AND user_id = ?");
                    $checkSubmit->execute([$activeExam['exam_id'], $user_id]);
                    $alreadySubmitted = intval($checkSubmit->fetchColumn()) > 0;
                    if ($alreadySubmitted) {
                        sendResponse('success', 'Exam already completed by student', null, 200);
                    }
                } catch (PDOException $submitEx) {
                    // Ignore if class_exam_results table doesn't exist yet
                }
            }
            // Calculate remaining time
            $activeExam['remaining_seconds'] = $expiryTime - $currentTime;
            
            // Fetch selected questions
            $questions = [];
            try {
                if (!empty($activeExam['selected_mcq_ids'])) {
                    $qIds = array_map('intval', explode(',', $activeExam['selected_mcq_ids']));
                    if (!empty($qIds)) {
                        $placeholders = implode(',', array_fill(0, count($qIds), '?'));
                        $qStmt = $pdo->prepare("
                            SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, image_url
                            FROM mcqs
                            WHERE mcq_id IN ($placeholders)
                        ");
                        $qStmt->execute($qIds);
                        $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } else {
                    // Fallback to first 40 questions in the chapter
                    $qStmt = $pdo->prepare("
                        SELECT mcq_id, chapter_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, image_url
                        FROM mcqs
                        WHERE chapter_id = ?
                        LIMIT 40
                    ");
                    $qStmt->execute([$activeExam['chapter_id']]);
                    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // Decode HTML entities
                foreach ($questions as &$q) {
                    $q['question'] = html_entity_decode($q['question'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['option_a'] = html_entity_decode($q['option_a'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['option_b'] = html_entity_decode($q['option_b'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['option_c'] = html_entity_decode($q['option_c'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['option_d'] = html_entity_decode($q['option_d'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['explanation'] = html_entity_decode($q['explanation'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $q['correct_answer'] = strtolower($q['correct_answer'] ?? '');
                }
                unset($q);
            } catch (PDOException $e) {
                error_log("Failed to load live exam questions: " . $e->getMessage());
            }

            $activeExam['questions'] = $questions;
            sendResponse('success', 'Active exam found', $activeExam, 200);
        }
    } else {
        sendResponse('success', 'No active exams', null, 200);
    }
} catch (PDOException $e) {
    // If table doesn't exist yet, just return no active exams
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        sendResponse('success', 'No active exams', null, 200);
    } else {
        error_log("Error checking live exam: " . $e->getMessage());
        sendResponse('error', 'Database error', null, 500);
    }
}
?>
