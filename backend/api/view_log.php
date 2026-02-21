<?php
$log_file = __DIR__ . '/../pdf_debug.log';
if (file_exists($log_file)) {
    echo nl2br(file_get_contents($log_file));
} else {
    echo "Log file not found at: $log_file";
}
?>
