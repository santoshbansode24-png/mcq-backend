<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'yamanote.proxy.rlwy.net';
$port = 24540;
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db   = 'railway';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected successfully to live database!\n\n";

    // 1. Show class_updates schema
    echo "--- class_updates schema ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE class_updates");
    $row = $stmt->fetch();
    echo $row['Create Table'] . "\n\n";

    // 2. Show class_exam_results schema (if exists)
    echo "--- class_exam_results schema ---\n";
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE class_exam_results");
        $row = $stmt->fetch();
        echo $row['Create Table'] . "\n\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }

    // 3. Test the first query in get_teacher_exams.php
    echo "--- Testing SELECT from class_updates ---\n";
    $teacher_id = 2;
    $examsStmt = $pdo->prepare("
        SELECT cu.id as update_id, cu.class_id, cu.title, cu.message, cu.created_at, c.class_name
        FROM class_updates cu
        LEFT JOIN classes c ON cu.class_id = c.class_id
        WHERE cu.teacher_id = ? AND cu.update_type = 'exam'
        ORDER BY cu.created_at DESC
    ");
    $examsStmt->execute([$teacher_id]);
    $exams = $examsStmt->fetchAll();
    echo "Found " . count($exams) . " exams.\n";
    print_r($exams);

} catch (Exception $e) {
    echo "GLOBAL ERROR: " . $e->getMessage() . "\n";
}
