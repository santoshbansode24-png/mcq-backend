<?php
// backend/api/sync_export.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

require_once '../config/db.php';

// SIMPLE SECURITY KEY
// This prevents random people from downloading your database.
$SECRET_KEY = "VEERU_SECURE_SYNC_2026"; 

$key = $_GET['key'] ?? '';
if ($key !== $SECRET_KEY) {
    sendResponse('error', 'Unauthorized: Invalid Sync Key', null, 403);
}

// 1. Get List of Tables Dynamically
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$export_data = [];

try {
    foreach ($tables as $table) {
        try {
            // Skip views or internal tables if needed
            
            $stmt = $pdo->prepare("SELECT * FROM `$table`");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $export_data[$table] = $rows;
        } catch (Exception $e) {
            // If a table fails, just skip it and log/continue
            // This prevents one bad table from stopping the whole backup
            // We could add a warning to the response if needed
            continue;
        }
    }

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('c'),
        'data' => $export_data
    ]);

} catch (Exception $e) {
    sendResponse('error', 'Export failed: ' . $e->getMessage(), null, 500);
}
?>
