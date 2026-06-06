<?php
require_once 'cors_middleware.php';
header('Content-Type: text/plain');

$log_file = '../login_debug.log';
if (file_exists($log_file)) {
    echo "--- LAST 50 LINES OF LOGIN DEBUG LOG ---\n";
    $lines = file($log_file);
    $last_lines = array_slice($lines, -50);
    echo implode("", $last_lines);
} else {
    echo "Log file not found at: " . realpath($log_file);
}
?>
