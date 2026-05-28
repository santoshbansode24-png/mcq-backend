<?php
/**
 * Join Classroom API (Student App)
 * Allows a student to join a specific teacher's classroom using a 6-digit code.
 */
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$data = getJsonInput();

// Required Fields
$missing = validateRequired($data, ['student_id', 'class_code']);
if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$student_id = intval($data['student_id']);
$class_code = strtoupper(sanitizeInput($data['class_code']));

try {
    // 1. Look up the class_code in classrooms table
    $stmt = $pdo->prepare("
        SELECT c.class_id, c.teacher_id, c.class_name, c.board, c.medium, c.class_level, u.school_name, u.name as teacher_name
        FROM classrooms c
        JOIN users u ON c.teacher_id = u.user_id
        WHERE c.class_code = ?
    ");
    $stmt->execute([$class_code]);
    $classroom = $stmt->fetch();

    if (!$classroom) {
        // Try fallback search in teacher_classes table (auto-heals/migrates codes)
        $stmt_tc = $pdo->prepare("
            SELECT tc.class_id as generic_class_id, tc.teacher_id, c.class_name, u.board_type as board, u.medium, u.school_name, u.name as teacher_name
            FROM teacher_classes tc
            JOIN users u ON tc.teacher_id = u.user_id
            JOIN classes c ON tc.class_id = c.class_id
            WHERE tc.class_code = ?
        ");
        $stmt_tc->execute([$class_code]);
        $fallback = $stmt_tc->fetch();
        
        if ($fallback) {
            $class_level = (int) filter_var($fallback['class_name'], FILTER_SANITIZE_NUMBER_INT);
            if ($class_level === 0) $class_level = $fallback['generic_class_id'];
            
            $stmt_ins = $pdo->prepare("
                INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $board_val = (strpos(strtoupper($fallback['board'] ?? ''), 'STATE') !== false) ? 'State Board' : 'CBSE';
            $medium_val = $fallback['medium'] ?? 'Marathi';
            if (!in_array($medium_val, ['Marathi', 'Semi-English', 'English'])) {
                $medium_val = 'Marathi';
            }
            
            $stmt_ins->execute([
                $fallback['teacher_id'],
                $class_code,
                $fallback['class_name'],
                $board_val,
                $medium_val,
                $class_level
            ]);
            
            // Re-fetch
            $stmt->execute([$class_code]);
            $classroom = $stmt->fetch();
        }
    }

    if (!$classroom) {
        sendResponse('error', 'Invalid Class Code. Please check and try again.', null, 404);
    }

    $class_id = $classroom['class_id'];
    $teacher_id = $classroom['teacher_id'];

    // 2. Insert into student_class_mapping (This explicitly binds the student to this exact teacher's classroom)
    try {
        $mapStmt = $pdo->prepare("INSERT INTO student_class_mapping (student_id, class_id) VALUES (?, ?)");
        $mapStmt->execute([$student_id, $class_id]);
    } catch (PDOException $e) {
        // 1062 = Duplicate entry, meaning student already joined this classroom. We can just ignore and proceed.
        if ($e->getCode() != '23000' && strpos($e->getMessage(), 'Duplicate entry') === false) {
            throw $e;
        }
    }

    // 3. Optional: Sync user's global profile with the classroom's board and medium so they get the right content
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET assigned_teacher_id = ?, 
            school_name = ?,
            board = ?,
            medium = ?,
            class_level = ?,
            subscription_status = 'active'
        WHERE user_id = ? AND user_type = 'student'
    ");
    
    $updateStmt->execute([
        $teacher_id,
        $classroom['school_name'],
        $classroom['board'],
        $classroom['medium'],
        $classroom['class_level'],
        $student_id
    ]);

    sendResponse('success', 'Successfully joined the classroom!', [
        'school_name' => $classroom['school_name'] ?? 'Your School',
        'teacher_name' => $classroom['teacher_name'],
        'class_name' => $classroom['class_name'],
        'classroom_id' => $class_id
    ]);

} catch (PDOException $e) {
    error_log("Join Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
