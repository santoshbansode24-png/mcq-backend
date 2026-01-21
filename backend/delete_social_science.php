<?php
/**
 * Delete Social Science from Scholarship - Secondary Level
 * Run this once to clean up the Railway database
 */

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
        echo "❌ Scholarship - Secondary class not found!\n";
        exit;
    }
    
    echo "✅ Found class: {$class['class_name']} (ID: {$class['class_id']})\n";
    
    // Delete Social Science for this class
    $deleteStmt = $pdo->prepare("
        DELETE FROM subjects 
        WHERE subject_name = 'Social Science' 
        AND class_id = ?
    ");
    $deleteStmt->execute([$class['class_id']]);
    
    $deleted = $deleteStmt->rowCount();
    
    if ($deleted > 0) {
        echo "✅ Successfully deleted {$deleted} 'Social Science' subject(s)!\n";
    } else {
        echo "ℹ️ No 'Social Science' subject found to delete.\n";
    }
    
    // Verify deletion
    $verifyStmt = $pdo->prepare("
        SELECT * FROM subjects 
        WHERE subject_name = 'Social Science' 
        AND class_id = ?
    ");
    $verifyStmt->execute([$class['class_id']]);
    $remaining = $verifyStmt->fetchAll();
    
    if (count($remaining) === 0) {
        echo "✅ Verification: Social Science successfully removed!\n";
    } else {
        echo "⚠️ Warning: {count($remaining)} Social Science records still exist.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
