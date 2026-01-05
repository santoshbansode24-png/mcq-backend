<?php
// Railway MySQL Import Tool
// This script imports videos and vocab_words tables to Railway MySQL

// Railway MySQL Credentials
$railway_host = 'yemanotc.proxy.rlwy.net';
$railway_port = 24548;
$railway_user = 'root';
$railway_pass = 'MvvImvVmcEUnrvMncVtVDbyYhqdcTuu7';
$railway_db = 'railway';

echo "<h1>Railway Database Import Tool</h1>";
echo "<p>Importing videos and vocab_words tables to Railway...</p>";

// Connect to Railway MySQL
$conn = new mysqli($railway_host, $railway_user, $railway_pass, $railway_db, $railway_port);

if ($conn->connect_error) {
    die("<p style='color:red'>❌ Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green'>✅ Connected to Railway MySQL successfully!</p>";

// Function to import SQL file
function importSQL($conn, $filename, $tableName) {
    echo "<h3>Importing $tableName...</h3>";
    
    if (!file_exists($filename)) {
        echo "<p style='color:red'>❌ File not found: $filename</p>";
        return false;
    }
    
    $sql = file_get_contents($filename);
    
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        echo "<p style='color:green'>✅ $tableName imported successfully!</p>";
        return true;
    } else {
        echo "<p style='color:red'>❌ Error importing $tableName: " . $conn->error . "</p>";
        return false;
    }
}

// Import videos table
$videosImported = importSQL($conn, __DIR__ . '/videos_export.sql', 'videos');

// Import vocab_words table
$vocabImported = importSQL($conn, __DIR__ . '/vocab_export.sql', 'vocab_words');

$conn->close();

echo "<hr>";
if ($videosImported && $vocabImported) {
    echo "<h2 style='color:green'>🎉 SUCCESS! All tables imported to Railway!</h2>";
    echo "<p>Your Railway database now has:</p>";
    echo "<ul>";
    echo "<li>✅ Videos data</li>";
    echo "<li>✅ Quick Revision (vocab_words) data</li>";
    echo "</ul>";
    echo "<p><strong>Next step:</strong> Switch your student app config to RAILWAY_CONFIG and test!</p>";
} else {
    echo "<h2 style='color:red'>⚠️ Some imports failed. Check the errors above.</h2>";
}
?>
