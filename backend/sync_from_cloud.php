<?php
// backend/sync_from_cloud.php

// CONFIG
$SYNC_URL = "https://api.veeruapp.in/api/sync_export.php?key=VEERU_SECURE_SYNC_2026";
$LOCAL_HOST = '127.0.0.1';
$LOCAL_USER = 'root';
$LOCAL_PASS = '';
$LOCAL_DB   = 'veeru_db';

echo "<h1>🔄 Veeru Cloud-to-Local HTTP Sync</h1>";

// 1. Establish Local Connection
$mysqli = new mysqli($LOCAL_HOST, $LOCAL_USER, $LOCAL_PASS, $LOCAL_DB);
if ($mysqli->connect_error) die("❌ Local DB Connection Failed: " . $mysqli->connect_error);

echo "<p>✅ Connected to Local Database.</p>";

// 2. Download Data
echo "<p>☁️ Downloading data from Cloud (via HTTPS)...</p>";

// Initialize CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $SYNC_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Handle local cert issues if any
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    die("❌ Failed to download data. HTTP Code: $http_code. Response: " . substr($response, 0, 100));
}

$json = json_decode($response, true);
if (!$json || !isset($json['status']) || $json['status'] !== 'success') {
    die("❌ Invalid JSON response from server. " . substr($response, 0, 100));
}

$data = $json['data'];
echo "<p style='color:green'>✅ Data downloaded successfully! (" . count($data) . " tables)</p>";

// 3. Import Data
$errors = [];

// Disable foreign key checks for bulk import
$mysqli->query("SET FOREIGN_KEY_CHECKS = 0");

foreach ($data as $table => $rows) {
    echo "Processing table: <b>$table</b>... ";
    
    // Safety check: Don't accidentally wipe 'users' if not intended (though users should be synced too)
    // For now, we sync everything in the export list.
    
    // Check if table exists locally
    $check_table = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($check_table->num_rows === 0) {
        echo "<span style='color:orange'>⚠️ Table '$table' missing locally. Skipping.</span><br>";
        continue;
    }

    // A. TRUNCATE TABLE
    if (!$mysqli->query("TRUNCATE TABLE `$table`")) {
        echo "<span style='color:red'>Failed to truncate: " . $mysqli->error . "</span><br>";
        $errors[] = $table;
        continue;
    }
    
    if (empty($rows)) {
        echo "Empty (0 rows).<br>";
        continue;
    }
    
    // B. PREPARE INSERT
    // Get columns from the first row
    $columns = array_keys($rows[0]);
    $col_names = implode("`, `", $columns);
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    
    $sql = "INSERT INTO `$table` (`$col_names`) VALUES ($placeholders)";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        echo "<span style='color:red'>Prepare failed: " . $mysqli->error . "</span><br>";
        $errors[] = $table;
        continue;
    }
    
    // C. INSERT ROWS
    $count = 0;
    foreach ($rows as $row) {
        // Bind params dynamically
        $types = "";
        $values = [];
        foreach ($row as $val) {
            if (is_int($val)) $types .= "i";
            elseif (is_double($val)) $types .= "d";
            else $types .= "s";
            $values[] = $val;
        }
        
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    echo "$count rows inserted.<br>";
    $stmt->close();
}

$mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
$mysqli->close();

if (empty($errors)) {
    echo "<h2 style='color:green'>🎉 SYNC COMPLETE! Your Local DB is up to date.</h2>";
} else {
    echo "<h2 style='color:red'>⚠️ Sync finished with errors in tables: " . implode(", ", $errors) . "</h2>";
}
?>
