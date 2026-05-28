<?php
/**
 * Join Class by Code API (Student)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['user_id', 'class_code'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$user_id = filter_var($input['user_id'], FILTER_VALIDATE_INT);
$class_code = strtoupper(sanitizeInput($input['class_code']));

try {
    // 1. Find the class and teacher based on the code
    $stmt = $pdo->prepare("
        SELECT c.teacher_id, c.class_id, c.class_name, u.school_name
        FROM classrooms c
        LEFT JOIN users u ON c.teacher_id = u.user_id AND u.user_type = 'teacher'
        WHERE c.class_code = ?
    ");
    $stmt->execute([$class_code]);
    $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classInfo) {
        // Try fallback search in teacher_classes table
        $stmt_tc = $pdo->prepare("
            SELECT tc.class_id as generic_class_id, tc.teacher_id, c.class_name, u.board_type as board, u.medium, u.school_name
            FROM teacher_classes tc
            JOIN users u ON tc.teacher_id = u.user_id
            JOIN classes c ON tc.class_id = c.class_id
            WHERE tc.class_code = ?
        ");
        $stmt_tc->execute([$class_code]);
        $fallback = $stmt_tc->fetch(PDO::FETCH_ASSOC);
        
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
            $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$classInfo) {
        sendResponse('error', 'Invalid Class Code. Please check and try again.', null, 404);
    }
    
    // Check if mapping exists
    $checkMap = $pdo->prepare("SELECT mapping_id FROM student_class_mapping WHERE student_id = ? AND class_id = ?");
    $checkMap->execute([$user_id, $classInfo['class_id']]);
    
    if (!$checkMap->fetch()) {
        $insertMap = $pdo->prepare("INSERT INTO student_class_mapping (student_id, class_id) VALUES (?, ?)");
        $insertMap->execute([$user_id, $classInfo['class_id']]);
    }

    // 2. Update the student's record (legacy fallback fields)
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET school_name = ?, class_id = ?
        WHERE user_id = ? AND user_type = 'student'
    ");
    $updated = $updateStmt->execute([$classInfo['school_name'], $classInfo['class_id'], $user_id]);

    sendResponse('success', 'Successfully joined Class ' . $classInfo['class_name'] . ' at ' . $classInfo['school_name'], [
        'school_name' => $classInfo['school_name'],
        'class_id' => $classInfo['class_id'],
        'class_name' => $classInfo['class_name']
    ], 200);

} catch (PDOException $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
