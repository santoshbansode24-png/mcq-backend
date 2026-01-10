<?php
// Quick script to create quick_revision table
header('Content-Type: text/plain');

$path = __DIR__ . '/config/db.php';
if (!file_exists($path)) {
    die("Error: config/db.php not found at $path");
}

require_once $path;

try {
    // Check connection (PDO object is created in db.php)
    if (!isset($pdo)) {
        die("Error: \$pdo object not found after including db.php");
    }

    echo "Connected successfully to database via PDO.\n";

    $sql = "CREATE TABLE IF NOT EXISTS quick_revision (
        revision_id INT PRIMARY KEY AUTO_INCREMENT,
        chapter_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        key_points JSON NOT NULL,
        summary TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "Table 'quick_revision' checked/created successfully.\n";
    
    // Check if data already exists for chapter 13
    $stmt = $pdo->prepare("SELECT count(*) FROM quick_revision WHERE chapter_id = 13");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    // Clear existing data to force update
    $pdo->exec("TRUNCATE TABLE quick_revision");
    echo "Table cleared for fresh data.\n";

    // Insert sample data
    $insertSQL = "INSERT INTO quick_revision (chapter_id, title, key_points, summary) VALUES
    (13, 'Geography Quick Revision', 
        ?,
        'This chapter covers basic geography concepts including capitals, natural processes, and planetary facts.'),
    (14, 'Science Quick Revision',
        ?,
        'Key scientific facts and formulas for quick revision before exams.')";
    
    $geoPoints = json_encode([
        ["q" => "What is the capital of France?", "a" => "Paris"],
        ["q" => "What process converts light to energy?", "a" => "Photosynthesis"],
        ["q" => "How many planets are in the solar system?", "a" => "8 planets"],
        ["q" => "How much of Earth surface is water?", "a" => "71%"],
        ["q" => "What is the highest peak on Earth?", "a" => "Mount Everest"]
    ]);
    
    $sciPoints = json_encode([
        ["q" => "What is the speed of light?", "a" => "299,792 km/s"],
        ["q" => "What does DNA stand for?", "a" => "Deoxyribonucleic Acid"],
        ["q" => "How many bones are in the human body?", "a" => "206 bones"],
        ["q" => "What is the acceleration due to gravity?", "a" => "9.8 m/s²"]
    ]);

    $stmt = $pdo->prepare($insertSQL);
    $stmt->execute([$geoPoints, $sciPoints]);
    
    echo "Sample Q&A data inserted successfully.\n";

} catch (PDOException $e) {
    echo "PDO Exception: " . $e->getMessage();
}
?>
