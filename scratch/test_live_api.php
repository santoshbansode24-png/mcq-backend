<?php
// Set up live database connection credentials in env
putenv("DB_HOST=yamanote.proxy.rlwy.net");
putenv("DB_PORT=24540");
putenv("DB_USER=root");
putenv("DB_PASSWORD=NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf");
putenv("DB_NAME=railway");

// Set up mock request context
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['class_id'] = 23;
$_GET['user_id'] = 36; // Santosh's user_id

echo "--- Running check_live_exam.php simulation with user_id = 36 ---\n";
chdir(__DIR__ . '/../backend/api/student');
require_once 'check_live_exam.php';
?>
