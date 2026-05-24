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

// 1. Get List of Tables Dynamically (Exclude Views)
$stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$export_data = [];

try {
    // Start JSON output
    echo '{';
    echo '"status":"success",';
    echo '"timestamp":"' . date('c') . '",';
    echo '"data":{';

    $firstTable = true;
    foreach ($tables as $table) {
        try {
            if (!$firstTable) {
                echo ',';
            }
            $firstTable = false;

            echo '"' . $table . '":[';
            
            // Use an unbuffered query if possible to save even more memory
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            
            $stmt = $pdo->prepare("SELECT * FROM `$table`");
            $stmt->execute();
            
            $firstRow = true;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$firstRow) {
                    echo ',';
                }
                $firstRow = false;
                echo json_encode($row);
            }
            echo ']';
            
            // Restore buffered queries
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            
        } catch (Exception $e) {
            // If a table fails, we just continue (it will leave an empty array or partial array, which isn't ideal for JSON strictly, but we catch it)
            // To keep JSON valid, if it fails before the array closes, we must close it.
            if ($firstRow) {
                echo ']'; // Close empty array if it failed immediately
            } else {
                echo ']'; // Close partially filled array
            }
            continue;
        }
    }

    echo '}}';

} catch (Exception $e) {
    // If headers already sent, we can't send a clean error response, but we can try to append an error field if possible
    die(']}],"error":"Export failed"}');
}
?>
