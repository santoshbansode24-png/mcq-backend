<?php
/**
 * Save / Update Attendance Session & Student Records
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        $input = $_POST;
    }

    $classId = intval($input['class_id'] ?? 0);
    $teacherId = intval($input['teacher_id'] ?? 0);
    $subjectId = isset($input['subject_id']) ? intval($input['subject_id']) : null;
    $sessionDate = trim($input['session_date'] ?? date('Y-m-d'));
    $records = $input['records'] ?? []; // Array of [{student_id: 1, status: 'P'/'A'/'L'/'E', remarks: ''}]

    if ($classId <= 0 || $teacherId <= 0) {
        throw new Exception("Class ID and Teacher ID are required.");
    }

    if (empty($records) || !is_array($records)) {
        throw new Exception("Attendance records list cannot be empty.");
    }

    // Compute summary stats
    $total = count($records);
    $pCount = 0;
    $aCount = 0;
    $lCount = 0;
    $eCount = 0;

    foreach ($records as $r) {
        $st = strtoupper($r['status'] ?? 'P');
        if ($st === 'P') $pCount++;
        elseif ($st === 'A') $aCount++;
        elseif ($st === 'L') $lCount++;
        elseif ($st === 'E') $eCount++;
        else $pCount++;
    }

    $pdo->beginTransaction();

    // 1. Create or Update attendance_session
    $sessionStmt = $pdo->prepare("
        SELECT session_id FROM attendance_sessions 
        WHERE class_id = ? AND session_date = ? AND (subject_id = ? OR (subject_id IS NULL AND ? IS NULL)) 
        LIMIT 1
    ");
    $sessionStmt->execute([$classId, $sessionDate, $subjectId, $subjectId]);
    $existingSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSession) {
        $sessionId = $existingSession['session_id'];
        $updateSession = $pdo->prepare("
            UPDATE attendance_sessions 
            SET teacher_id = ?, total_students = ?, present_count = ?, absent_count = ?, late_count = ?, leave_count = ?
            WHERE session_id = ?
        ");
        $updateSession->execute([$teacherId, $total, $pCount, $aCount, $lCount, $eCount, $sessionId]);
    } else {
        $insertSession = $pdo->prepare("
            INSERT INTO attendance_sessions 
            (class_id, subject_id, teacher_id, session_date, total_students, present_count, absent_count, late_count, leave_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertSession->execute([$classId, $subjectId, $teacherId, $sessionDate, $total, $pCount, $aCount, $lCount, $eCount]);
        $sessionId = $pdo->lastInsertId();
    }

    // 2. Insert or Replace student attendance records
    $recordStmt = $pdo->prepare("
        INSERT INTO attendance_records (session_id, student_id, status, remarks)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
    ");

    foreach ($records as $r) {
        $stId = intval($r['student_id']);
        $status = strtoupper($r['status'] ?? 'P');
        $remarks = trim($r['remarks'] ?? '');
        $recordStmt->execute([$sessionId, $stId, $status, $remarks]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance saved successfully.',
        'session_id' => $sessionId,
        'summary' => [
            'total' => $total,
            'present' => $pCount,
            'absent' => $aCount,
            'late' => $lCount,
            'leave' => $eCount,
            'percentage' => $total > 0 ? round(($pCount / $total) * 100, 1) : 0
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
