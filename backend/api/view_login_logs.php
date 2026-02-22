<?php
/**
 * Diagnostic tool to view the last few login attempts from the log file.
 */
header('Content-Type: text/plain');

$log_file = '../login_debug.log';

if (file_exists($log_file)) {
    // Read the last 2000 characters to get the latest attempts
    $content = file_get_contents($log_file, false, null, max(0, filesize($log_file) - 4000));
    echo "--- LAST LOGIN ATTEMPTS ---\n";
    echo $content;
} else {
    echo "Log file not found at: " . realpath($log_file);
}
?>
