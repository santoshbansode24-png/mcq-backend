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
    // 1. Ensure schema supports division_name
    try {
        $pdo->query("SELECT division_name FROM teacher_classes LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE teacher_classes ADD COLUMN division_name VARCHAR(50) DEFAULT NULL");
    }

    // 2. Ensure schema supports class_code
    try {
        $pdo->query("SELECT class_code FROM teacher_classes LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE teacher_classes ADD COLUMN class_code VARCHAR(10) DEFAULT NULL");
    }

    // 2.5 Drop unique key to allow multiple divisions of the same class
    try {
        $pdo->exec("ALTER TABLE teacher_classes DROP INDEX unique_teacher_class");
    } catch (PDOException $e) {
        // Ignore
    }

    // 3. Generate Unique 6-Digit Code
    $is_unique = false;
    $class_code = '';
    while (!$is_unique) {
        $class_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
        $check = $pdo->prepare("SELECT count(*) FROM teacher_classes WHERE class_code = ?");
        $check->execute([$class_code]);
        if ($check->fetchColumn() == 0) {
            $is_unique = true;
        }
    }

    // 4. Insert the new classroom mapping
    // We don't use UNIQUE KEY on teacher_id + class_id alone anymore, because a teacher might teach 
    // multiple divisions of the SAME class (e.g., Class 3 Rose, Class 3 Tulip).
    // Let's just insert it.
    $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, class_code, division_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $class_id, $class_code, $division_name]);

    // Fetch School Name for context
    $t_stmt = $pdo->prepare("SELECT school_name, name FROM users WHERE user_id = ?");
    $t_stmt->execute([$teacher_id]);
    $teacher_info = $t_stmt->fetch();

    $c_stmt = $pdo->prepare("SELECT class_name FROM classes WHERE class_id = ?");
    $c_stmt->execute([$class_id]);
    $class_name = $c_stmt->fetchColumn();

    sendResponse('success', 'Classroom created successfully!', [
        'class_code' => $class_code,
        'school_name' => $teacher_info['school_name'] ?? 'Your School',
        'teacher_name' => $teacher_info['name'],
        'class_name' => $class_name,
        'division_name' => $division_name
    ]);

} catch (PDOException $e) {
    error_log("Create Classroom Error: " . $e->getMessage());
    sendResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
}
?>
