<?php
// Health Check 
// Does not depend on Database
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$boond = "/app/uploads/notes/1771612437_Boond_The_Water_Cycle_Story_compressed.pdf";
$boond_exists = file_exists($boond);

echo json_encode([
    'status' => 'ok',
    'service' => 'Veeru Backend',
    'version' => '3.0-PDF-Fix',
    'timestamp' => time(),
    'message' => 'Backend is reachable!',
    'diagnostic' => [
        'boond_at_root' => $boond_exists ? 'YES' : 'NO',
        'boond_size' => $boond_exists ? filesize($boond) : 0,
        'root_notes_dir' => is_dir("/app/uploads/notes") ? 'YES' : 'NO'
    ]
]);
?>
