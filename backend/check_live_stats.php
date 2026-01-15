<?php
// check_live_stats.php

// Railway MySQL Credentials (from import_to_railway.php)
$railway_host = 'yamanote.proxy.rlwy.net';
$railway_port = 24540;
$railway_user = 'root';
$railway_pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$railway_db = 'railway';

echo "Connecting to Railway MySQL...\n";

$conn = new mysqli($railway_host, $railway_user, $railway_pass, $railway_db, $railway_port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Connected successfully.\n";
echo "------------------------------------------------\n";
echo "LIVE DATABASE STATISTICS\n";
echo "------------------------------------------------\n";

// Count Chapters
$result = $conn->query("SELECT COUNT(*) as count FROM chapters");
$row = $result->fetch_assoc();
echo "Total Chapters: " . $row['count'] . "\n";

// Count Videos
$result = $conn->query("SELECT COUNT(*) as count FROM videos");
$row = $result->fetch_assoc();
echo "Total Videos:   " . $row['count'] . "\n";

// Count Classes by Board
$result = $conn->query("SELECT board_type, COUNT(*) as count FROM classes GROUP BY board_type");
if ($result) {
    echo "\nClasses per Board:\n";
    while ($row = $result->fetch_assoc()) {
        echo " - " . ($row['board_type'] ? $row['board_type'] : 'NULL') . ": " . $row['count'] . "\n";
    }
}

$conn->close();
?>
