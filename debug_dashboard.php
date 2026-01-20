<?php
require_once 'backend/config/db.php';

echo "<h2>Debug Data</h2>";

// Check Classes
echo "<h3>Classes with board_type='Scholarship'</h3>";
$stmt = $pdo->prepare("SELECT * FROM classes WHERE board_type = ?");
$stmt->execute(['Scholarship']);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($classes, true) . "</pre>";

// Check All Classes
echo "<h3>All Classes (First 50)</h3>";
$stmt = $pdo->query("SELECT * FROM classes LIMIT 50");
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($all, true) . "</pre>";

// Check Subjects for Class 38
echo "<h3>Subjects for Class 38</h3>";
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE class_id = 38");
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($subjects, true) . "</pre>";
?>
