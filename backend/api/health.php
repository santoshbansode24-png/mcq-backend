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
        'root_notes_dir' => is_dir("/app/uploads/notes") ? 'YES' : 'NO',
        'generate_specific_exists' => file_exists(__DIR__ . '/generate_specific_type.php') ? 'YES' : 'NO',
        'gd_loaded' => extension_loaded('gd') ? 'YES' : 'NO',
        'imagecreatefromstring_exists' => function_exists('imagecreatefromstring') ? 'YES' : 'NO',
        'getimagesize_exists' => function_exists('getimagesize') ? 'YES' : 'NO',
        'ai_config_hash' => file_exists(__DIR__ . '/../config/ai_config.php') ? md5_file(__DIR__ . '/../config/ai_config.php') : 'MISSING',
        'root_ai_config_hash' => file_exists('/app/config/ai_config.php') ? md5_file('/app/config/ai_config.php') : 'MISSING'
    ]
]);
?>
