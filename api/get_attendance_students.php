<?php
/**
 * Get Students List for Attendance Session
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    $classId = isset($_REQUEST['class_id']) ? intval($_REQUEST['class_id']) : 0;
    $date = isset($_REQUEST['date']) ? trim($_REQUEST['date']) : date('Y-m-d');
    $subjectId = isset($_REQUEST['subject_id']) ? intval($_REQUEST['subject_id']) : null;

    if ($classId <= 0) {
        throw new Exception('Invalid Class ID');
    }

    // 1. Check if session already exists for this class and date
    $sessionStmt = $pdo->prepare("SELECT * FROM attendance_sessions WHERE class_id = ? AND session_date = ? AND (subject_id = ? OR (subject_id IS NULL AND ? IS NULL)) LIMIT 1");
    $sessionStmt->execute([$classId, $date, $subjectId, $subjectId]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    $existingRecords = [];
    if ($session) {
        $recStmt = $pdo->prepare("SELECT student_id, status, remarks FROM attendance_records WHERE session_id = ?");
        $recStmt->execute([$session['session_id']]);
        while ($r = $recStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingRecords[$r['student_id']] = [
                'status' => $r['status'],
                'remarks' => $r['remarks']
            ];
        }
    }

    // 2. Fetch all students registered in this class/classroom
    $studentsStmt = $pdo->prepare("
        SELECT 
            COALESCE(u.id, u.user_id) as student_id,
            COALESCE(u.full_name, u.name) as full_name,
            u.email,
            COALESCE(u.phone, u.mobile) as phone,
            u.roll_number,
            u.profile_picture
        FROM student_classroom_mapping scm
        JOIN users u ON scm.student_id = COALESCE(u.id, u.user_id)
        WHERE scm.class_id = ? AND scm.status = 'active'
        ORDER BY CAST(COALESCE(u.roll_number, '999999') AS UNSIGNED) ASC, COALESCE(u.full_name, u.name) ASC
    ");
    $studentsStmt->execute([$classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: If no students in mapping table, search users table directly by class_id
    if (empty($students)) {
        $fallbackStmt = $pdo->prepare("
            SELECT 
                COALESCE(id, user_id) as student_id,
                COALESCE(full_name, name) as full_name,
                email,
                COALESCE(phone, mobile) as phone,
                roll_number,
                profile_picture
            FROM users
            WHERE class_id = ? AND (user_type = 'student' OR role = 'student')
            ORDER BY CAST(COALESCE(roll_number, '999999') AS UNSIGNED) ASC, COALESCE(full_name, name) ASC
        ");
        $fallbackStmt->execute([$classId]);
        $students = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Format output student list
    $formattedStudents = [];
    $rollIndex = 1;
    foreach ($students as $stu) {
        $sId = $stu['student_id'];
        $status = isset($existingRecords[$sId]) ? $existingRecords[$sId]['status'] : 'P'; // Default Present
        $remarks = isset($existingRecords[$sId]) ? $existingRecords[$sId]['remarks'] : '';

        $formattedStudents[] = [
            'student_id' => intval($sId),
            'full_name' => $stu['full_name'] ?: 'Student #' . $sId,
            'roll_number' => $stu['roll_number'] ?: (string)$rollIndex,
            'email' => $stu['email'] ?: '',
            'phone' => $stu['phone'] ?: '',
            'profile_picture' => $stu['profile_picture'] ?: '',
            'status' => $status,
            'remarks' => $remarks
        ];
        $rollIndex++;
    }

    echo json_encode([
        'success' => true,
        'class_id' => $classId,
        'session_date' => $date,
        'session' => $session ?: null,
        'total_students' => count($formattedStudents),
        'students' => $formattedStudents
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
