<?php
require_once 'backend/config/db.php';
try {
    $stmt1 = $pdo->query("SHOW CREATE TABLE class_updates");
    $cu = $stmt1->fetch(PDO::FETCH_ASSOC);
    echo "--- class_updates ---\n";
    echo $cu['Create Table'] . "\n\n";
    
    $stmt2 = $pdo->query("SHOW CREATE TABLE notifications");
    $nt = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "--- notifications ---\n";
    echo $nt['Create Table'] . "\n\n";
    
    $stmt3 = $pdo->query("SHOW CREATE TABLE student_class_mapping");
    $scm = $stmt3->fetch(PDO::FETCH_ASSOC);
    echo "--- student_class_mapping ---\n";
    echo $scm['Create Table'] . "\n\n";
    
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
