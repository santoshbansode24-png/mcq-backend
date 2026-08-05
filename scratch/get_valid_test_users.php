<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "1. TEACHERS IN USERS TABLE:\n";
$teachers = $pdo->query("SELECT user_id, name, phone_number, user_type FROM users WHERE user_type = 'teacher' LIMIT 5")->fetchAll();
print_r($teachers);

echo "\n2. STUDENTS IN USERS TABLE:\n";
$students = $pdo->query("SELECT user_id, name, phone_number, user_type FROM users WHERE user_type = 'student' LIMIT 5")->fetchAll();
print_r($students);

echo "\n3. CLASSROOMS IN CLASSROOMS TABLE:\n";
$classrooms = $pdo->query("SELECT class_id, teacher_id, class_code, class_name FROM classrooms ORDER BY class_id DESC LIMIT 5")->fetchAll();
print_r($classrooms);
?>
