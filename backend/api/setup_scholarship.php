<?php
/**
 * Setup Scholarship Classes on Railway
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Create 3 distinct Scholarship Classes
    $pdo->exec("
        INSERT INTO classes (class_id, class_name, board_type) VALUES 
        (38, 'Scholarship - Primary Level (1-4)', 'Scholarship'),
        (39, 'Scholarship - Upper Primary Level (5-7)', 'Scholarship'),
        (40, 'Scholarship - Secondary Level (8-10)', 'Scholarship')
        ON DUPLICATE KEY UPDATE class_name = VALUES(class_name)
    ");
    
    // Seed Subjects for Primary Level (ID 38)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 38), ('Mathematics', 38), ('Mental Ability', 38), ('General Knowledge', 38)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    
    // Seed Subjects for Upper Primary Level (ID 39)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 39), ('Mathematics', 39), ('Science', 39), ('Mental Ability', 39), ('General Knowledge', 39)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    
    // Seed Subjects for Secondary Level (ID 40)
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('English', 40), ('Mathematics', 40), ('Science', 40), ('Mental Ability', 40), ('General Knowledge', 40), ('Social Science', 40)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    
    // Seed Mock Tests subject for ALL levels
    $pdo->exec("
        INSERT INTO subjects (subject_name, class_id) VALUES 
        ('Mock Tests', 38), ('Mock Tests', 39), ('Mock Tests', 40)
        ON DUPLICATE KEY UPDATE subject_name = subject_name
    ");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Scholarship classes and subjects created successfully!',
        'classes_created' => 3,
        'subjects_created' => 'Multiple subjects across all levels'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
