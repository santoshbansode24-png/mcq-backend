<?php
chdir(__DIR__ . '/backend/api');
$_GET['user_id'] = 48; 
$_GET['folder_id'] = 'root';
ob_start();
require 'get_pdf_study_status.php';
$out = ob_get_clean();
echo "Total Output Length: " . strlen($out) . " bytes\n";
?>
