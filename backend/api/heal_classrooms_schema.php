<?php
/**
 * Self-healing database migration script
 * Aligns classrooms.class_level with generic class_id from classes table.
 * Can be run via browser URL after deployment.
 */
require_once '../config/db.php';
require_once 'cors_middleware.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

try {
    $stmt = $pdo->query("SELECT * FROM classrooms");
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $healed = [];
    $skipped = [];
    $warnings = [];

    foreach ($classrooms as $classroom) {
        $class_id = $classroom['class_id'];
        $class_name = $classroom['class_name'];
        $board = $classroom['board'];
        $medium = $classroom['medium'];
        $current_level = intval($classroom['class_level']);
        
        // Extract grade number from name (e.g. "CLASS 10" or "Class 10 - Div A" -> 10)
        $grade_num = (int) filter_var($class_name, FILTER_SANITIZE_NUMBER_INT);
        if ($grade_num === 0) {
            $skipped[] = "Classroom $class_id ($class_name) - no numeric grade found";
            continue;
        }
        
        // Map board & medium to classes.board_type format
        $board_type = 'STATE_MARATHI';
        if ($board === 'CBSE') {
            $board_type = 'CBSE';
        } elseif ($board === 'State Board' && $medium === 'Semi-English') {
            $board_type = 'STATE_SEMI';
        } elseif ($board === 'State Board' && $medium === 'Marathi') {
            $board_type = 'STATE_MARATHI';
        }
        
        // Find matching generic class
        $stmt_class = $pdo->prepare("
            SELECT class_id 
            FROM classes 
            WHERE board_type = ? AND (class_name LIKE ? OR class_name LIKE ?) 
            LIMIT 1
        ");
        $stmt_class->execute([$board_type, "%Class $grade_num%", "%CLASS $grade_num%"]);
        $generic_class_id = $stmt_class->fetchColumn();
        
        if ($generic_class_id) {
            $generic_class_id = (int)$generic_class_id;
            if ($current_level !== $generic_class_id) {
                $update = $pdo->prepare("UPDATE classrooms SET class_level = ? WHERE class_id = ?");
                $update->execute([$generic_class_id, $class_id]);
                $healed[] = "Classroom $class_id ($class_name): class_level updated from $current_level to $generic_class_id";
            } else {
                $skipped[] = "Classroom $class_id ($class_name) is already correct (class_level = $generic_class_id)";
            }
        } else {
            $warnings[] = "Could not find matching generic class for classroom $class_id ($class_name, $board, $medium)";
        }
    }
    
    sendResponse('success', 'Database healing migration completed successfully', [
        'healed_count' => count($healed),
        'healed_details' => $healed,
        'skipped_count' => count($skipped),
        'skipped_details' => $skipped,
        'warnings_count' => count($warnings),
        'warnings_details' => $warnings
    ]);
} catch (Exception $e) {
    sendResponse('error', 'Fatal Error during database healing: ' . $e->getMessage(), null, 500);
}
?>
