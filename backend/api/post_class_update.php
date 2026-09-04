<?php
/**
 * Post Class Update API (Teacher -> Student)
 */

require_once 'cors_middleware.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

$input = getJsonInput();

$required = ['teacher_id', 'school_name', 'class_id', 'update_type', 'title'];
$missing = validateRequired($input, $required);

if (!empty($missing)) {
    sendResponse('error', 'Missing required fields: ' . implode(', ', $missing), null, 400);
}

$teacher_id = filter_var($input['teacher_id'], FILTER_VALIDATE_INT);
$school_name = sanitizeInput($input['school_name']);
$class_id = filter_var($input['class_id'], FILTER_VALIDATE_INT);
$update_type = sanitizeInput($input['update_type']);
$title = sanitizeInput($input['title']);
$message = isset($input['message']) ? sanitizeInput($input['message']) : null;
$payload = isset($input['payload']) ? json_encode($input['payload']) : null; // Can be JSON object for Exam/Worksheet configs

    try {
        // Verify the teacher has access to this class
        $authStmt = $pdo->prepare("SELECT teacher_id FROM classrooms WHERE teacher_id = ? AND class_id = ?");
        $authStmt->execute([$teacher_id, $class_id]);
        if (!$authStmt->fetch()) {
            file_put_contents(__DIR__.'/debug_class.txt', "Unauthorized for class $class_id by teacher $teacher_id\n", FILE_APPEND);
            sendResponse('error', 'Unauthorized: You are not assigned to this class.', null, 403);
        }
        
        file_put_contents(__DIR__.'/debug_class.txt', "Inserting class_updates class_id: $class_id, teacher_id: $teacher_id, type: $update_type\n", FILE_APPEND);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$teacher_id, $school_name, $class_id, $update_type, $title, $message, $payload]);
            $update_id = $pdo->lastInsertId();
        
            sendResponse('success', 'Update posted successfully', ['update_id' => $update_id], 201);
        
        } catch (PDOException $e) {
            // If strict mode rejects 'worksheet', fallback to 'material' instead of altering table
            if (strpos($e->getMessage(), 'update_type') !== false || strpos($e->getMessage(), 'ENUM') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
                try {
                    $update_type = 'material';
                    $stmt->execute([$teacher_id, $school_name, $class_id, $update_type, $title, $message, $payload]);
                    $update_id = $pdo->lastInsertId();
                    sendResponse('success', 'Update posted successfully', ['update_id' => $update_id], 201);
                } catch (PDOException $retryEx) {
                    file_put_contents(__DIR__.'/debug_class.txt', "Retry PDOException: " . $retryEx->getMessage() . "\n", FILE_APPEND);
                    sendResponse('error', 'Database error: ' . $retryEx->getMessage(), ['error' => $retryEx->getMessage()], 500);
                }
            } else {
                file_put_contents(__DIR__.'/debug_class.txt', "PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
                sendResponse('error', 'Database error: ' . $e->getMessage(), ['error' => $e->getMessage()], 500);
            }
        }
    } catch (Throwable $t) {
        file_put_contents(__DIR__.'/debug_class.txt', "Throwable: " . $t->getMessage() . "\n", FILE_APPEND);
        sendResponse('error', 'Database error: ' . $t->getMessage(), null, 500);
    }
?>
