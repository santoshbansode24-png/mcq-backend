<?php
// Simulate the teacher app calling create_live_class.php
$url = 'https://api.veeruapp.in/backend/api/teacher/create_live_class.php';

$data = [
    'teacher_id' => 1,
    'class_id' => 10,
    'title' => 'Test Live Class'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response Body:\n";
echo $response . "\n";
?>
