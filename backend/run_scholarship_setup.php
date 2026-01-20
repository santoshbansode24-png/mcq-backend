<?php
require_once 'config/db.php';

try {
    $sql = file_get_contents('setup_scholarship.sql');
    if (!$sql) {
        die("Error: Could not read setup_scholarship.sql");
    }

    // Execute the SQL
    $pdo->exec($sql);
    echo "Success: Scholarship & Olympiad data seeded successfully.\n";

    // verify
    $stmt = $pdo->query("SELECT class_id, class_name FROM classes WHERE class_name = 'Scholarship & Olympiad Corner'");
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($class) {
        echo "Class Created: " . $class['class_name'] . " (ID: " . $class['class_id'] . ")\n";
    }

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
