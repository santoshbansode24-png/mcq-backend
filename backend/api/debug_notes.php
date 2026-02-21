header('Content-Type: text/plain');
require_once '../config/db.php';

echo "Database Host: " . (getenv('DB_HOST') ?: '127.0.0.1') . "\n";
echo "Database Name: " . (getenv('DB_NAME') ?: 'veeru_db') . "\n";

try {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY created_at DESC LIMIT 10");
    $notes = $stmt->fetchAll();
    
    echo "Count: " . count($notes) . "\n\n";
    foreach ($notes as $note) {
        echo "ID: " . $note['note_id'] . " | ";
        echo "Title: " . $note['title'] . " | ";
        echo "Source: " . ($note['source'] ?? 'N/A') . " | ";
        echo "Path: " . $note['file_path'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
