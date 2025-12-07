<?php
require_once 'config/db.php';

// Check existing classes
$stmt = $pdo->query("SELECT class_name FROM classes");
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h3>Current Classes in DB:</h3>";
echo "<ul>";
foreach ($existing as $c) {
    echo "<li>$c</li>";
}
echo "</ul>";

// List of all desired classes
$desired = [
    'Class 1', 'Class 2', 'Class 3', 'Class 4', 
    'Class 5', 'Class 6', 'Class 7', 'Class 8', 
    'Class 9', 'Class 10', 'Class 11', 'Class 12'
];

$added = [];

foreach ($desired as $className) {
    if (!in_array($className, $existing)) {
        // Prepare insert carefully to avoid ID collisions if possible, 
        // relying on auto-increment is safest unless we need specific IDs.
        $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
        $stmt->execute([$className]);
        $added[] = $className;
    }
}

if (!empty($added)) {
    echo "<h3>Added the following missing classes:</h3>";
    echo "<ul>";
    foreach ($added as $a) {
        echo "<li>$a</li>";
    }
    echo "</ul>";
} else {
    echo "<h3>All classes are already present!</h3>";
}

echo "<p>Done. Please reload the app.</p>";
?>
