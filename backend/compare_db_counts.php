<?php
// compare_db_counts.php

// 1. Local DB Connection
$local = new mysqli('127.0.0.1', 'root', '', 'veeru_db');
if ($local->connect_error) die("Local Connection Failed: " . $local->connect_error);

// 2. Railway DB Connection (Credentials from force_import_to_railway.php)
// I will verify these from the file content I read earlier or re-read it if needed.
// Assuming standard railway creds. I'll read the file first to be safe, but 
// for now let's use the ones I can infer or just include the config file if possible.
// Better to just inline the credentials if I assume them, or better, read them from the file.

// READING CREDENTIALS FROM force_import_to_railway.php STRATEY:
// I'll grab them from the file content I saw earlier:
// $railway_host = 'roundhouse.proxy.rlwy.net';
// $railway_user = 'root';
// $railway_pass = 'yKkZtHDrgSmkKMvKjZlWwXWqPzOqIuJq';
// $railway_port = 59560;
// $railway_db   = 'railway';

$railway_host = 'roundhouse.proxy.rlwy.net';
$railway_user = 'root';
$railway_pass = 'yKkZtHDrgSmkKMvKjZlWwXWqPzOqIuJq';
$railway_port = 59560;
$railway_db   = 'railway';

$remote = new mysqli($railway_host, $railway_user, $railway_pass, $railway_db, $railway_port);
if ($remote->connect_error) die("Remote Connection Failed: " . $remote->connect_error);

$tables = ['videos', 'notes', 'mcqs', 'subjects', 'chapters', 'vocab_words', 'flashcards'];

echo "Comparison (Local vs Remote):\n";
echo sprintf("%-15s | %-10s | %-10s\n", "Table", "Local", "Remote");
echo str_repeat("-", 40) . "\n";

foreach ($tables as $table) {
    $local_count = $local->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
    $remote_count = $remote->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
    
    echo sprintf("%-15s | %-10s | %-10s\n", $table, $local_count, $remote_count);
}

$local->close();
$remote->close();
?>
