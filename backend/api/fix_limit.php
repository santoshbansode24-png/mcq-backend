<?php
$conn = new mysqli('yamanote.proxy.rlwy.net', 'root', 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf', 'railway', 24540);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_free_request_limit_daily', '500') ON DUPLICATE KEY UPDATE setting_value = '500'");
$conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_global_limit_daily', '500000') ON DUPLICATE KEY UPDATE setting_value = '500000'");

echo "SUCCESS! The database has been updated.\n";
$conn->close();
?>
