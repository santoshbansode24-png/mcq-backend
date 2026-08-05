<?php
$url = 'https://api.veeruapp.in/backend/api/teacher/upload_class_material.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'teacher_id' => 2,
    'class_id' => 10,
    'title' => 'Math Revision Worksheet',
    'message' => 'Please solve this worksheet.',
    'update_type' => 'worksheet'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

echo "FORM DATA RESPONSE FROM LIVE API:\n$res\n";
?>
