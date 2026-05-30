<?php
require 'config/db.php';

$pdo->query("INSERT IGNORE INTO classrooms (class_id, class_name) VALUES (10, 'Class 10')");

$stmt = $pdo->query("SELECT user_id FROM users WHERE user_type = 'teacher' LIMIT 1");
$teacher_id = $stmt->fetchColumn();

if (!$teacher_id) {
    die("No teacher found in database.");
}

$url = 'http://localhost/veeru/api/teacher/upload_class_material.php';
$data = [
    'teacher_id' => $teacher_id,
    'class_id' => 10,
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
