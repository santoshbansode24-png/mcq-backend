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
// The frontend sends 'class_id' which is the generic ID from 'classes' table. We use this to get class_name and map it to class_level.
$input_class_id = intval($data['class_id']);
$division_name = isset($data['division_name']) ? sanitizeInput($data['division_name']) : '';
$new_name = isset($data['name']) ? sanitizeInput($data['name']) : null;
$new_mobile = isset($data['mobile']) ? sanitizeInput($data['mobile']) : null;

try {
    // 0. Update Profile if provided
    if ($new_name || $new_mobile) {
        $updateFields = [];
        $params = [];
        if ($new_name) {
            $updateFields[] = "name = ?";
            $params[] = $new_name;
        }
        if ($new_mobile) {
            $updateFields[] = "mobile = ?";
            $params[] = $new_mobile;
        }
        $params[] = $teacher_id;
        $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?")->execute($params);
    }

    // 1. Fetch Teacher Info (to get board and medium)
    $t_stmt = $pdo->prepare("SELECT school_name, name, board, medium FROM users WHERE user_id = ?");
    $t_stmt->execute([$teacher_id]);
    $teacher_info = $t_stmt->fetch();
    
    $board = $teacher_info['board'] ?? 'State Board';
    $medium = $teacher_info['medium'] ?? 'Marathi';
    $school_name = $teacher_info['school_name'] ?? 'Your School';
    $teacher_name = $teacher_info['name'];

    // 2. Fetch Class Name from existing generic classes table
    $c_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
    $c_stmt->execute([$input_class_id]);
    $class_name = $c_stmt->fetchColumn() ?: "Class $input_class_id";
    
    // Attempt to extract numeric class level from class name (e.g. "Class 3" -> 3)
    $class_level = (int) filter_var($class_name, FILTER_SANITIZE_NUMBER_INT);
    if ($class_level === 0) $class_level = $input_class_id;

    // Optional: Append division name to the class name for clarity
    $full_class_name = $division_name ? "$class_name - $division_name" : $class_name;

    // 3. Generate Unique 6-Digit Code
    $is_unique = false;
    $class_code = '';
    while (!$is_unique) {
        $class_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
        $check = $pdo->prepare("SELECT count(*) FROM classrooms WHERE class_code = ?");
        $check->execute([$class_code]);
        if ($check->fetchColumn() == 0) {
            $is_unique = true;
        }
    }

    // 4. Insert into new `classrooms` table!
    $stmt = $pdo->prepare("
        INSERT INTO classrooms (teacher_id, class_code, class_name, board, medium, class_level) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$teacher_id, $class_code, $full_class_name, $board, $medium, $class_level]);
    $new_classroom_id = $pdo->lastInsertId();

    // 5. Also insert into teacher_classes for compatibility
    try {
        $stmt_tc = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, class_code, division_name) VALUES (?, ?, ?, ?)");
        $stmt_tc->execute([$teacher_id, $input_class_id, $class_code, $division_name]);
    } catch (PDOException $e) {
        error_log("Teacher Classes Sync Insert Error: " . $e->getMessage());
    }

    sendResponse('success', 'Classroom created successfully!', [
        'class_code' => $class_code,
        'school_name' => $school_name,
        'teacher_name' => $teacher_name,
        'class_name' => $full_class_name,
        'division_name' => $division_name,
        'classroom_id' => $new_classroom_id
    ]);

} catch (PDOException $e) {
    error_log("Create Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
