<?php
require_once __DIR__ . '/../config/db.php';

// Prepare export file path
$export_file = __DIR__ . '/export_notes_for_railway.sql';

try {
    // 1. Get all notes that have valid S3, Drive, or URL paths (ignore old local paths)
    $stmt = $pdo->query("SELECT * FROM notes WHERE file_path LIKE 'http%'");
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notes)) {
        die("No cloud-hosted notes found to export. Run the migration script first!\n");
    }

    $sql_output = "-- Veeru Notes Update (S3 Links)\n";
    $sql_output .= "DELETE FROM notes;\n"; // Safety: Clear old local notes to avoid duplicates/confusion? Or maybe just TRUNCATE.
    // Actually, TRUNCATE is better to reset IDs, but we might want to preserve IDs if other tables ref them.
    // Let's us REPLACE INTO or just TRUNCATE if notes are standalone.
    // Notes track chapter_id.
    
    $sql_output .= "TRUNCATE TABLE notes;\n";

    foreach ($notes as $note) {
        $n_id = $pdo->quote($note['note_id']);
        $c_id = $pdo->quote($note['chapter_id']);
        $title = $pdo->quote($note['title']);
        $type = $pdo->quote($note['note_type']);
        $path = $pdo->quote($note['file_path']);
        // Handle potential NULL or missing columns if schema changed, but assuming standard
        $content = isset($note['content']) ? $pdo->quote($note['content']) : "''";
        $created = isset($note['created_at']) ? $pdo->quote($note['created_at']) : "NOW()";

        $sql_output .= "INSERT INTO notes (note_id, chapter_id, title, note_type, file_path, content, created_at) VALUES ($n_id, $c_id, $title, $type, $path, $content, $created);\n";
    }

    file_put_contents($export_file, $sql_output);
    echo "✅ Successfully exported " . count($notes) . " notes to:\n$export_file\n";
    echo "\nCopy the content of this file and run it in your Railway Database Query tab.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
