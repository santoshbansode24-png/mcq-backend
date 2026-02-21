header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY created_at DESC LIMIT 20");
    $notes = $stmt->fetchAll();
    
    echo json_encode([
        'db_host' => getenv('DB_HOST'),
        'db_name' => getenv('DB_NAME'),
        'count' => count($notes),
        'notes' => $notes
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
