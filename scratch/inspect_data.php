<?php
$host = 'yamanote.proxy.rlwy.net';
$port = 24540;
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$db   = 'railway';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Check live_exams schema
    echo "--- live_exams schema ---\n";
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE live_exams");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
    
    // Check count of live_exams by status
    echo "--- live_exams records count ---\n";
    try {
        $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM live_exams GROUP BY status");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }

    // Check class_exam_results records
    echo "--- class_exam_results records ---\n";
    try {
        $stmt = $pdo->query("SELECT * FROM class_exam_results");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }

    // Run test query from get_teacher_exams.php
    echo "--- get_teacher_exams query test ---\n";
    try {
        $stmt = $pdo->prepare("
            SELECT cu.id as update_id, cu.class_id, cu.title, cu.message, cu.created_at, c.class_name
            FROM class_updates cu
            LEFT JOIN classes c ON cu.class_id = c.class_id
            WHERE cu.teacher_id = ? AND cu.update_type IN ('exam', 'live_exam')
        ");
        $stmt->execute([1]);
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
