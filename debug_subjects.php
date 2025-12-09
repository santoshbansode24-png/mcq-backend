<?php
// backend/debug_subjects.php
require_once 'config/db.php';

echo "<h1>Debug Subjects</h1>";

$class_id = 10; // Default to Class 10 as per our reset script
if (isset($_GET['class_id'])) {
    $class_id = $_GET['class_id'];
}

echo "Looking for subjects in <strong>Class ID: $class_id</strong><br><br>";

// 1. Check if table exists
try {
    $check = $pdo->query("SHOW TABLES LIKE 'subjects'");
    if ($check->rowCount() > 0) {
        echo "✅ Table 'subjects' exists.<br>";
    } else {
        echo "❌ Table 'subjects' does NOT exist!<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "<br>";
}

// 2. Query Subjects
try {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Query Results:</h3>";
    if (count($subjects) > 0) {
        echo "✅ Found " . count($subjects) . " subjects:<br>";
        echo "<pre>";
        print_r($subjects);
        echo "</pre>";
    } else {
        echo "⚠️ No subjects found for Class ID $class_id.<br>";
        
        // Check if ANY subjects exist
        $all = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
        echo "Total subjects in database: $all<br>";
    }

} catch (PDOException $e) {
    echo "❌ SQL Error: " . $e->getMessage();
}
?>
