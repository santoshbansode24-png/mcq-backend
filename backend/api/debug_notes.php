<?php
require_once 'cors_middleware.php';
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY created_at DESC LIMIT 10");
    $notes = $stmt->fetchAll();
    
    echo "<h1>Last 10 Notes</h1>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>File Path</th><th>Type</th><th>Created At</th></tr>";
    foreach ($notes as $note) {
        echo "<tr>";
        echo "<td>" . $note['note_id'] . "</td>";
        echo "<td>" . $note['title'] . "</td>";
        echo "<td>" . htmlspecialchars($note['file_path']) . "</td>";
        echo "<td>" . $note['note_type'] . "</td>";
        echo "<td>" . $note['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
