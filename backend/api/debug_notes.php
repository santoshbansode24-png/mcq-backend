// header('Content-Type: text/plain'); // Removed for browser visibility
require_once '../config/db.php';

echo "<h1>Debug Info</h1>";
echo "Database Host: " . (getenv('DB_HOST') ?: '127.0.0.1') . "<br>";
echo "Database Name: " . (getenv('DB_NAME') ?: 'veeru_db') . "<br>";

try {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY created_at DESC LIMIT 10");
    $notes = $stmt->fetchAll();
    
    echo "<h2>Notes (Count: " . count($notes) . ")</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>Path</th></tr>";
    foreach ($notes as $note) {
        echo "<tr>";
        echo "<td>" . $note['note_id'] . "</td>";
        echo "<td>" . htmlspecialchars($note['title']) . "</td>";
        echo "<td>" . htmlspecialchars($note['file_path']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
