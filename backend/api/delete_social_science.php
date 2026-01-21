<?php
/**
 * Delete Social Science from Scholarship - Secondary Level
 * API endpoint to clean up Railway database
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Find the class_id for Scholarship - Secondary
    $stmt = $pdo->prepare("
        SELECT class_id, class_name 
        FROM classes 
        WHERE board_type = 'SCHOLARSHIP' 
        AND class_name LIKE '%Secondary%'
    ");
    $stmt->execute();
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Scholarship - Secondary class not found!'
        ]);
        exit;
    }
    
    // Delete Social Science for this class
    $deleteStmt = $pdo->prepare("
        DELETE FROM subjects 
        WHERE subject_name = 'Social Science' 
        AND class_id = ?
    ");
    $deleteStmt->execute([$class['class_id']]);
    
    $deleted = $deleteStmt->rowCount();
    
    echo json_encode([
        'status' => 'success',
        'message' => $deleted > 0 
            ? "Successfully deleted {$deleted} 'Social Science' subject(s)!" 
            : "No 'Social Science' subject found to delete.",
        'class_name' => $class['class_name'],
        'class_id' => $class['class_id'],
        'deleted_count' => $deleted
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
