<?php
header('Content-Type: application/json');

require_once '../config/db.php';

// Get the most recent uploaded PDF
$stmt = $pdo->query("
    SELECT note_id, title, file_path, created_at 
    FROM notes 
    WHERE note_type = 'pdf' 
    AND file_path NOT LIKE 'http%' 
    ORDER BY created_at DESC 
    LIMIT 1
");
$note = $stmt->fetch();

$result = [
    'database_entry' => $note,
    'file_check' => null,
    'generated_url' => null,
];

if ($note && !empty($note['file_path'])) {
    $file_path = __DIR__ . '/../' . $note['file_path'];
    $result['file_check'] = [
        'full_path' => $file_path,
        'exists' => file_exists($file_path),
        'readable' => is_readable($file_path),
        'size' => file_exists($file_path) ? filesize($file_path) : 0,
    ];
    
    // Generate the URL that get_notes.php would create
    $host = 'api.veeruapp.in';
    $protocol = 'https';
    $result['generated_url'] = $protocol . '://' . $host . '/backend/api/serve_pdf.php?file=' . urlencode($note['file_path']);
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
