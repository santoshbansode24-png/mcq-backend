<?php
$host = 'roundhouse.proxy.rlwy.net';
$user = 'root';
$pass = 'yKkZtHDrgSmkKMvKjZlWwXWqPzOqIuJq';
$port = 59560;
$db   = 'railway';

echo "Attempting PHP Connection...\n";

$mysqli = new mysqli($host, $user, $pass, $db, $port);

if ($mysqli->connect_error) {
    die("❌ Connection Failed: " . $mysqli->connect_error);
}

echo "✅ PHP Connection Success! Host info: " . $mysqli->host_info . "\n";
$mysqli->close();
?>
