<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../cors_middleware.php';

try {
    $live_exam_id = 0;
    if (isset($_GET['live_exam_id'])) {
        $live_exam_id = (int)$_GET['live_exam_id'];
    } elseif (isset($_GET['exam_id'])) {
        $live_exam_id = (int)$_GET['exam_id'];
    } elseif (isset($_GET['id'])) {
        $live_exam_id = (int)$_GET['id'];
    } elseif (isset($_GET['update_id'])) {
        $live_exam_id = (int)$_GET['update_id'];
    }
    
    if ($live_exam_id === 0) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }
    
    // First get the live exam details
    $examStmt = $pdo->prepare("SELECT * FROM live_exams WHERE id = ?");
    $examStmt->execute([$live_exam_id]);
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        // Fallback: check if live_exam_id is a class_update ID containing an exam_id in payload
        $cuStmt = $pdo->prepare("SELECT payload FROM class_updates WHERE update_id = ?");
        $cuStmt->execute([$live_exam_id]);
        $cuRow = $cuStmt->fetch(PDO::FETCH_ASSOC);
        if ($cuRow && !empty($cuRow['payload'])) {
            $payloadData = json_decode($cuRow['payload'], true);
            $realExamId = (int)($payloadData['exam_id'] ?? 0);
            if ($realExamId > 0) {
                $examStmt->execute([$realExamId]);
                $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
                if ($exam) {
                    $live_exam_id = $realExamId;
                }
            }
        }
    }
    
    if (!$exam) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }
    
    $class_id = $exam['class_id'];
    
    // Self-healing: Ensure class_exam_results table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS class_exam_results (
            result_id INT AUTO_INCREMENT PRIMARY KEY,
            update_id INT NOT NULL,
            user_id INT NOT NULL,
            correct INT DEFAULT 0,
            incorrect INT DEFAULT 0,
            unanswered INT DEFAULT 0,
            total INT DEFAULT 0,
            time_seconds INT DEFAULT 0,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            UNIQUE KEY unique_attempt (update_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Calculate overall class rankings for all students in this class
    $stmt_cr = $pdo->prepare("SELECT COUNT(*) FROM classrooms WHERE class_id = ?");
    $stmt_cr->execute([$class_id]);
    $is_classroom = $stmt_cr->fetchColumn() > 0;

    if ($is_classroom) {
        $overallQuery = "SELECT 
                            u.user_id, 
                            COALESCE(SUM(ms.mcq_score), 0) as overall_score
                          FROM users u
                          JOIN student_class_mapping scm ON u.user_id = scm.student_id
                          LEFT JOIN student_progress ms ON u.user_id = ms.user_id
                          WHERE scm.class_id = ? AND u.user_type = 'student'
                          GROUP BY u.user_id
                          ORDER BY overall_score DESC";
    } else {
        $overallQuery = "SELECT 
                            u.user_id, 
                            COALESCE(SUM(ms.mcq_score), 0) as overall_score
                          FROM users u
                          LEFT JOIN student_progress ms ON u.user_id = ms.user_id
                          WHERE u.class_id = ? AND u.user_type = 'student'
                          GROUP BY u.user_id
                          ORDER BY overall_score DESC";
    }
    
    $overallStmt = $pdo->prepare($overallQuery);
    $overallStmt->execute([$class_id]);
    $overallResults = $overallStmt->fetchAll(PDO::FETCH_ASSOC);

    $overallRankMap = [];
    $overallScoreMap = [];
    $oRank = 1;
    foreach ($overallResults as $oRow) {
        $uId = (int)$oRow['user_id'];
        $overallRankMap[$uId] = $oRank++;
        $overallScoreMap[$uId] = (int)$oRow['overall_score'];
    }

    // Find all potential matching update_ids (either live_exams.id or class_updates.update_id)
    $updateIdsToMatch = [$live_exam_id];
    try {
        $cuStmt = $pdo->prepare("SELECT update_id FROM class_updates WHERE update_type = 'live_exam' AND (payload LIKE ? OR payload LIKE ?)");
        $cuStmt->execute(['%"exam_id":' . $live_exam_id . '%', '%"exam_id": "' . $live_exam_id . '"%']);
        $cuIds = $cuStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cuIds as $cId) {
            $updateIdsToMatch[] = (int)$cId;
        }
    } catch (Throwable $t) {}

    $placeholders = implode(',', array_fill(0, count($updateIdsToMatch), '?'));

    // Query actual submissions from class_exam_results for this live exam
    $query = "SELECT 
                u.user_id as id, 
                u.name as full_name, 
                r.correct as total_score,
                r.incorrect,
                r.unanswered,
                r.total,
                r.time_seconds,
                CASE WHEN r.total > 0 THEN ROUND((r.correct / r.total * 100), 2) ELSE 0.0 END as percentage
              FROM users u
              JOIN class_exam_results r ON u.user_id = r.user_id
              WHERE r.update_id IN ($placeholders)
                AND u.user_type = 'student'
              ORDER BY total_score DESC, percentage DESC, r.time_seconds ASC
              LIMIT 50";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute($updateIdsToMatch);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $rank = 1;

    if ($result) {
        foreach ($result as $row) {
            $uId = (int)$row['id'];
            $row['rank'] = $rank;
            $row['exam_rank'] = $rank++;
            $row['overall_rank'] = $overallRankMap[$uId] ?? null;
            $row['overall_score'] = $overallScoreMap[$uId] ?? 0;
            $row['total_score'] = (int)$row['total_score'];
            $row['incorrect'] = (int)($row['incorrect'] ?? 0);
            $row['unanswered'] = (int)($row['unanswered'] ?? 0);
            $row['total'] = (int)($row['total'] ?? 0);
            $row['time_seconds'] = (int)($row['time_seconds'] ?? 0);
            $row['percentage'] = (float)$row['percentage'];
            $leaderboard[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $leaderboard]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal Error: ' . $e->getMessage()
    ]);
}
?>
