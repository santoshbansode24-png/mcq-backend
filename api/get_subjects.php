<?php
/**
 * Get Subjects API
 * Veeru
 * 
 * Endpoint: GET /api/get_subjects.php?class_id=1
 * Purpose: Get all subjects for a specific class
 */

require_once __DIR__ . '/cors_middleware.php';
require_once __DIR__ . '/../config/db.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

// Get class_id from query parameter
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Validate class_id
if ($class_id <= 0) {
    sendResponse('error', 'Valid class_id is required', null, 400);
}

// Map classroom ID to generic class ID only if class_id is NOT a standard class
try {
    $stmt_check_class = $pdo->prepare("SELECT class_id FROM classes WHERE class_id = ? LIMIT 1");
    $stmt_check_class->execute([$class_id]);
    $is_standard_class = (bool)$stmt_check_class->fetchColumn();

    if (!$is_standard_class) {
        $stmt_generic = $pdo->prepare("SELECT class_level, board, medium FROM classrooms WHERE class_id = ? LIMIT 1");
        $stmt_generic->execute([$class_id]);
        $cr = $stmt_generic->fetch(PDO::FETCH_ASSOC);
        if ($cr && !empty($cr['class_level'])) {
            $board_type = 'CBSE';
            if (stripos($cr['board'] ?? '', 'State') !== false) {
                $board_type = (stripos($cr['medium'] ?? '', 'Semi') !== false) ? 'STATE_SEMI' : 'STATE_MARATHI';
            }
            $stmt_find = $pdo->prepare("SELECT class_id FROM classes WHERE board_type = ? AND (class_name LIKE ? OR class_name LIKE ?) LIMIT 1");
            $stmt_find->execute([$board_type, "%Class {$cr['class_level']}%", "%CLASS {$cr['class_level']}%"]);
            $found_id = $stmt_find->fetchColumn();
            if ($found_id) {
                $class_id = (int)$found_id;
            } else {
                $class_id = (int)$cr['class_level'];
            }
        }
    }
} catch (PDOException $e) {
    // Fail silently and use original class_id if classrooms tables don't exist
}

try {
    // Query subjects for the class with optimized stats fetching
    $stmt = $pdo->prepare("
        SELECT 
            s.subject_id,
            s.subject_name,
            s.description,
            s.class_id,
            c.class_name,
            COUNT(DISTINCT ch.chapter_id) as total_chapters,
            COUNT(m.mcq_id) as total_mcqs
        FROM subjects s
        INNER JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN chapters ch ON s.subject_id = ch.subject_id
        LEFT JOIN mcqs m ON ch.chapter_id = m.chapter_id
        WHERE s.class_id = ?
        GROUP BY s.subject_id
        ORDER BY s.subject_name ASC
    ");
    
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
    
    // Check if subjects exist
    if (empty($subjects)) {
        sendResponse('success', 'No subjects found for this class', [], 200);
    }
    
    // Success response
    sendResponse('success', 'Subjects retrieved successfully', $subjects, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>