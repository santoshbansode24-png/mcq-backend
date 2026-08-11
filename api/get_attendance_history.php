<?php
/**
 * Get Attendance History & Monthly Grid Report
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    $classId = isset($_REQUEST['class_id']) ? intval($_REQUEST['class_id']) : 0;
    $month = isset($_REQUEST['month']) ? trim($_REQUEST['month']) : date('Y-m'); // e.g. 2026-08

    if ($classId <= 0) {
        throw new Exception("Invalid Class ID");
    }

    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    // 1. Fetch all attendance sessions in this month for the class
    $sessionsStmt = $pdo->prepare("
        SELECT session_id, session_date, present_count, absent_count, late_count, leave_count, total_students
        FROM attendance_sessions
        WHERE class_id = ? AND session_date BETWEEN ? AND ?
        ORDER BY session_date ASC
    ");
    $sessionsStmt->execute([$classId, $startDate, $endDate]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all students in class
    $studentsStmt = $pdo->prepare("
        SELECT u.id as student_id, u.full_name, u.roll_number
        FROM student_classroom_mapping scm
        JOIN users u ON scm.student_id = u.id
        WHERE scm.class_id = ? AND scm.status = 'active'
        ORDER BY CAST(COALESCE(u.roll_number, '999999') AS UNSIGNED) ASC, u.full_name ASC
    ");
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        $fallbackStmt = $pdo->prepare("
            SELECT id as student_id, full_name, roll_number
            FROM users
            WHERE class_id = ? AND role = 'student'
            ORDER BY CAST(COALESCE(roll_number, '999999') AS UNSIGNED) ASC, full_name ASC
        ");
        $fallbackStmt->execute([$classId]);
        $students = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Build Attendance Matrix
    $matrix = [];
    $totalSessionsCount = count($sessions);

    foreach ($students as $index => $stu) {
        $sId = intval($stu['student_id']);
        $matrix[$sId] = [
            'student_id' => $sId,
            'full_name' => $stu['full_name'] ?: 'Student #' . $sId,
            'roll_number' => $stu['roll_number'] ?: (string)($index + 1),
            'dates' => [],
            'total_present' => 0,
            'total_absent' => 0,
            'total_late' => 0,
            'total_leave' => 0,
            'attendance_percentage' => 0
        ];
    }

    if ($totalSessionsCount > 0) {
        $sessionIds = array_column($sessions, 'session_id');
        $inClause = implode(',', array_fill(0, count($sessionIds), '?'));
        
        $recStmt = $pdo->prepare("
            SELECT ar.session_id, ar.student_id, ar.status, s.session_date
            FROM attendance_records ar
            JOIN attendance_sessions s ON ar.session_id = s.session_id
            WHERE ar.session_id IN ($inClause)
        ");
        $recStmt->execute($sessionIds);
        
        while ($row = $recStmt->fetch(PDO::FETCH_ASSOC)) {
            $sId = intval($row['student_id']);
            $dateStr = $row['session_date'];
            $st = $row['status'];

            if (isset($matrix[$sId])) {
                $matrix[$sId]['dates'][$dateStr] = $st;
                if ($st === 'P') $matrix[$sId]['total_present']++;
                elseif ($st === 'A') $matrix[$sId]['total_absent']++;
                elseif ($st === 'L') $matrix[$sId]['total_late']++;
                elseif ($st === 'E') $matrix[$sId]['total_leave']++;
            }
        }
    }

    // Calculate percentage and low attendance flag
    $lowAttendanceList = [];
    foreach ($matrix as &$stuData) {
        $totAttended = $stuData['total_present'] + ($stuData['total_late'] * 0.5);
        $stuData['attendance_percentage'] = $totalSessionsCount > 0 ? round(($totAttended / $totalSessionsCount) * 100, 1) : 0;
        
        if ($totalSessionsCount >= 3 && $stuData['attendance_percentage'] < 75) {
            $lowAttendanceList[] = $stuData;
        }
    }

    echo json_encode([
        'success' => true,
        'class_id' => $classId,
        'month' => $month,
        'total_sessions' => $totalSessionsCount,
        'sessions' => $sessions,
        'students_matrix' => array_values($matrix),
        'low_attendance_alerts' => $lowAttendanceList
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
