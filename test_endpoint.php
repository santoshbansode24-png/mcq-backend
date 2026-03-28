<?php
chdir(__DIR__ . '/backend/api');
$_GET['user_id'] = 48; // Assuming user_id is 48
$_GET['folder_id'] = 'root';
require 'get_pdf_study_status.php';
echo "\nJSON Error: " . json_last_error_msg() . "\n";
?>
