<?php 
require_once __DIR__ . '/../config/db.php';
global $pdo;
if ($pdo) {
    echo "Success: Local DB Connected!";
} else {
    echo "Fail: Local DB Connection failed!";
}
?>
