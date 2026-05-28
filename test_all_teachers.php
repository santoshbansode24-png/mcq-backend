<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT user_id FROM users WHERE user_type = 'teacher'");
$teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($teachers as $tid) {
    $ch = curl_init('https://api.veeruapp.in/api/teacher/get_classes.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['teacher_id' => $tid]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode != 200) {
        echo "Teacher $tid FAILED! HTTP $httpcode\n";
        echo $response . "\n";
    }
}
echo "Done testing all teachers.\n";
?>
