<?php
require 'config/db.php';

$stmt = $pdo->query("SELECT user_id FROM users WHERE user_type = 'teacher' LIMIT 1");
$teacher_id = $stmt->fetchColumn();

if (!$teacher_id) {
    die("No teacher found in database.");
}

$stmt2 = $pdo->query("SELECT class_id FROM classrooms LIMIT 1");
$class_id = $stmt2->fetchColumn();

if (!$class_id) {
    // maybe try 'classes' table if 'classrooms' is empty
    $stmt3 = $pdo->query("SELECT class_id FROM classes LIMIT 1");
    $class_id = $stmt3->fetchColumn();
}

if (!$class_id) {
    die("No class found in database.");
}

$url = 'http://localhost/veeru/api/teacher/upload_class_material.php';
$data = [
    'teacher_id' => $teacher_id,
    'class_id' => $class_id,
    'title' => 'Test Worksheet',
    'message' => 'Please do this.',
    'update_type' => 'worksheet'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo "Response from API: " . $response;
?>
