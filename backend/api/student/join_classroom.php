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

$student_id = intval($data['student_id'] ?? 0);
$class_code = strtoupper(trim(sanitizeInput($data['class_code'] ?? '')));

if ($student_id <= 0) {
    sendResponse('error', 'Valid student_id is required', null, 400);
}

if (empty($class_code)) {
    sendResponse('error', 'Valid class_code is required', null, 400);
}

try {
    // 1. Look up the class_code in classrooms table (using LEFT JOIN so missing teacher profile won't cause lookup failure)
    $stmt = $pdo->prepare("
        SELECT c.class_id, c.teacher_id, c.class_name, c.board, c.medium, c.class_level, u.school_name, u.name as teacher_name
        FROM classrooms c
        LEFT JOIN users u ON c.teacher_id = u.user_id
        WHERE UPPER(TRIM(c.class_code)) = ?
    ");
    $stmt->execute([$class_code]);
    $classroom = $stmt->fetch();

    if (!$classroom) {
        // Try fallback search in teacher_classes table (auto-heals/migrates codes)
        $stmt_tc = $pdo->prepare("
            SELECT tc.class_id as generic_class_id, tc.teacher_id, c.class_name, u.board_type as board, u.medium, u.school_name, u.name as teacher_name
            FROM teacher_classes tc
            LEFT JOIN users u ON tc.teacher_id = u.user_id
            LEFT JOIN classes c ON tc.class_id = c.class_id
            WHERE UPPER(TRIM(tc.class_code)) = ?
        ");
        $stmt_tc->execute([$class_code]);
        $fallback = $stmt_tc->fetch();
        
        if ($fallback) {
            $class_level = $fallback['generic_class_id'] ?: 10;
            $class_name = $fallback['class_name'] ?: ("Class " . $class_level);
            
            $board_val = (strpos(strtoupper($fallback['board'] ?? ''), 'CBSE') !== false) ? 'CBSE' : 'State Board';
            $medium_val = $fallback['medium'] ?? 'Marathi';
            if (!in_array($medium_val, ['Marathi', 'Semi-English', 'English'])) {
                $medium_val = 'Marathi';
            }
            
            try {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([
                    $fallback['teacher_id'],
                    $class_code,
                    $class_name,
                    $board_val,
                    $medium_val,
                    $class_level
                ]);
            } catch (PDOException $insErr) {
                // If already exists or error occurs during insertion, log and continue to re-fetch
                error_log("Auto-heal classrooms insert fallback notice: " . $insErr->getMessage());
            }
            
            // Re-fetch from classrooms table
            $stmt->execute([$class_code]);
            $classroom = $stmt->fetch();

            // If re-fetch after insert fails (e.g. classrooms missing row), build transient classroom array
            if (!$classroom) {
                $classroom = [
                    'class_id' => $class_level,
                    'teacher_id' => $fallback['teacher_id'],
                    'class_name' => $class_name,
                    'board' => $board_val,
                    'medium' => $medium_val,
                    'class_level' => $class_level,
                    'school_name' => $fallback['school_name'] ?? 'Your School',
                    'teacher_name' => $fallback['teacher_name'] ?? 'Teacher'
                ];
            }
        }
    }

    if (!$classroom) {
        sendResponse('error', 'Invalid Class Code. Please check and try again.', null, 404);
    }

    $class_id = $classroom['class_id'];
    $teacher_id = $classroom['teacher_id'];

    // 2. Insert into student_class_mapping (binds the student to this classroom)
    try {
        $mapStmt = $pdo->prepare("INSERT INTO student_class_mapping (student_id, class_id) VALUES (?, ?)");
        $mapStmt->execute([$student_id, $class_id]);
    } catch (PDOException $e) {
        // Ignore duplicate entry (student already joined)
        if ($e->getCode() != '23000' && strpos($e->getMessage(), 'Duplicate entry') === false) {
            error_log("student_class_mapping insert notice: " . $e->getMessage());
        }
    }

    // 3. Sync student profile (class_id, school_name, board_type)
    $board_val = $classroom['board'] ?? 'State Board';
    $medium_val = $classroom['medium'] ?? 'Marathi';

    $board_type_val = 'STATE_MARATHI';
    if ($board_val === 'CBSE') {
        $board_type_val = 'CBSE';
    } elseif ($board_val === 'State Board' && $medium_val === 'Semi-English') {
        $board_type_val = 'STATE_SEMI';
    } elseif ($board_val === 'State Board' && $medium_val === 'Marathi') {
        $board_type_val = 'STATE_MARATHI';
    }

    $school_name_val = !empty($classroom['school_name']) ? $classroom['school_name'] : 'Your School';
    $teacher_name_val = !empty($classroom['teacher_name']) ? $classroom['teacher_name'] : 'Teacher';

    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET class_id = ?, 
            school_name = ?,
            board_type = ?,
            board = ?
        WHERE user_id = ?
    ");
    
    $updateStmt->execute([
        $classroom['class_level'],
        $school_name_val,
        $board_type_val,
        $board_val,
        $student_id
    ]);

    sendResponse('success', 'Successfully joined the classroom!', [
        'school_name' => $school_name_val,
        'teacher_name' => $teacher_name_val,
        'class_name' => $classroom['class_name'],
        'class_id' => $classroom['class_level'],
        'board_type' => $board_type_val
    ]);

} catch (PDOException $e) {
    error_log("Join Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
