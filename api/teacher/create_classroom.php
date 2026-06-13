<?php
/**
 * Create Classroom API (Teacher App)
 * Allows a teacher to generate a 6-digit class code for a specific Class and Division.
 */
require_once '../../config/db.php';
require_once '../cors_middleware.php';

$data = getJsonInput();

// Required Fields
$missing = validateRequired($data, ['teacher_id', 'class_id']);
if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = intval($data['teacher_id']);
$class_id = intval($data['class_id']);
$division_name = isset($data['division_name']) ? sanitizeInput($data['division_name']) : '';

try {


    // 3. Generate Unique 6-Digit Code
    $is_unique = false;
    $class_code = '';
    while (!$is_unique) {
        $class_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
        $check = $pdo->prepare("SELECT count(*) FROM teacher_classes WHERE class_code = ?");
        $check->execute([$class_code]);
        $check2 = $pdo->prepare("SELECT count(*) FROM classrooms WHERE class_code = ?");
        $check2->execute([$class_code]);
        if ($check->fetchColumn() == 0 && $check2->fetchColumn() == 0) {
            $is_unique = true;
        }
    }

    // 4. Fetch Teacher & Class Info
    $t_stmt = $pdo->prepare("SELECT school_name, name, board, medium FROM users WHERE user_id = ?");
    $t_stmt->execute([$teacher_id]);
    $teacher_info = $t_stmt->fetch();
    
    if (!$teacher_info) {
        sendResponse('error', 'Teacher account not found. Please log in again.', null, 404);
    }
    
    $board = (!empty($teacher_info['board'])) ? $teacher_info['board'] : 'State Board';
    $medium = (!empty($teacher_info['medium'])) ? $teacher_info['medium'] : 'Marathi';
    $school_name = (!empty($teacher_info['school_name'])) ? $teacher_info['school_name'] : 'Your School';
    $teacher_name = $teacher_info['name'] ?? 'Teacher';

    $c_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
    $c_stmt->execute([$class_id]);
    $class_name = $c_stmt->fetchColumn() ?: "Class $class_id";
    
    $class_level = (int) filter_var($class_name, FILTER_SANITIZE_NUMBER_INT);
    if ($class_level === 0) $class_level = $class_id;

    $full_class_name = $division_name ? "$class_name - $division_name" : $class_name;

    // 5. Insert the new classroom mapping in teacher_classes
    $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, class_code, division_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $class_id, $class_code, $division_name]);

    // 6. Insert into classrooms table for student joining
    try {
        $stmt_c = $pdo->prepare("
            INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_c->execute([$teacher_id, $class_code, $full_class_name, $board, $medium, $class_level]);
        $new_classroom_id = $pdo->lastInsertId();
    } catch (PDOException $e) {
        // If classrooms table has issues, log it but don't fail the whole request
        error_log("Classrooms Insert Error: " . $e->getMessage());
    }

    sendResponse('success', 'Classroom created successfully!', [
        'class_code' => $class_code,
        'school_name' => $school_name,
        'teacher_name' => $teacher_name,
        'class_name' => $full_class_name,
        'division_name' => $division_name,
        'classroom_id' => $new_classroom_id ?? null
    ]);

} catch (PDOException $e) {
    error_log("Create Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
