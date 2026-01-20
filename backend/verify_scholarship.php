<?php
require_once 'config/db.php';

echo "<h2>Scholarship Data Verification</h2>";

// First, run the setup
try {
    $sql = file_get_contents(__DIR__ . '/setup_scholarship.sql');
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ SQL executed successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Check classes
echo "<h3>Classes with board_type='Scholarship':</h3>";
$stmt = $pdo->query("SELECT * FROM classes WHERE board_type = 'Scholarship'");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($classes, true) . "</pre>";
echo "<p><strong>Count: " . count($classes) . "</strong></p>";

// Check subjects
echo "<h3>Subjects for Scholarship Classes:</h3>";
$stmt = $pdo->query("SELECT s.*, c.class_name FROM subjects s JOIN classes c ON s.class_id = c.class_id WHERE c.board_type = 'Scholarship'");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($subjects, true) . "</pre>";
echo "<p><strong>Count: " . count($subjects) . "</strong></p>";
?>
