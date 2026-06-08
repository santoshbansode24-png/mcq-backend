<?php
$railway_host = 'yamanote.proxy.rlwy.net';
$railway_port = 24540;
$railway_user = 'root';
$railway_pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$railway_db = 'railway';

try {
    $dsn = "mysql:host=$railway_host;port=$railway_port;dbname=$railway_db;charset=utf8mb4";
    $pdo = new PDO($dsn, $railway_user, $railway_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Fetch the latest teacher_id and class_id to make sure the student sees it
    $stmt = $pdo->query("SELECT teacher_id, class_id, school_name FROM class_updates ORDER BY update_id DESC LIMIT 1");
    $latest = $stmt->fetch();
    
    if (!$latest) {
        die("No previous class updates found to clone class_id from.");
    }
    
    $teacher_id = $latest['teacher_id'];
    $class_id = $latest['class_id'];
    $school_name = $latest['school_name'];
    
    $title = "Test PDF Document";
    $message = "Please tap 'Download PDF' below to verify that the attachment viewer is opening documents correctly.";
    $update_type = "pdf";
    $payload = json_encode([
        "file_url" => "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf",
        "file_name" => "sample_attachment.pdf"
    ]);
    
    $insert = $pdo->prepare("
        INSERT INTO class_updates (teacher_id, school_name, class_id, update_type, title, message, payload, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $insert->execute([$teacher_id, $school_name, $class_id, $update_type, $title, $message, $payload]);
    
    echo "✅ Success! Inserted test class update.\n";
    echo "Class ID: $class_id\n";
    echo "Teacher ID: $teacher_id\n";
    echo "Attachment URL: https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
