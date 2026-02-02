<?php
// Pull Data from Live (Railway) to Local (XAMPP)
// This script safely READS from Live and WRITES to Local.

function prompt($label) {
    echo $label;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

// Increase memory limit for large exports
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0);

echo "========================================\n";
echo "   VEERU LIVE -> LOCAL DATA SYNC        \n";
echo "========================================\n\n";
echo "SAFE MODE: This will ONLY overwrite your LOCAL database.\n";
echo "Your LIVE Railway database will be untouched (Read-Only).\n\n";

// 1. Get Live Credentials
echo "Please enter your LIVE Railway Credentials:\n";
echo "(You can get these from Railway Dashboard -> Variables)\n\n";

$live_host = prompt("Paste Live Host (e.g. junction.proxy.rlwy.net): ");
$live_port = prompt("Paste Live Port (e.g. 56712): ");
$live_user = prompt("Paste Live User (default 'root'): ");
if (empty($live_user)) $live_user = 'root';
$live_pass = prompt("Paste Live Password: ");
$live_name = 'railway';

// 2. Connect to Live Server to Export Data
echo "\n[Step 1/3] Connecting to LIVE Railway Server...\n";

try {
    $mysqli_live = new mysqli($live_host, $live_user, $live_pass, $live_name, (int)$live_port);
    if ($mysqli_live->connect_error) {
        die("\n[ERROR] Could not connect to LIVE server: " . $mysqli_live->connect_error . "\n");
    }
    echo "[OK] Connected to Live Server!\n";
    echo "Exporting data... (This may take a minute)\n";

    // Get all tables and identify views
    $tables = [];
    $views = [];
    $result = $mysqli_live->query("SHOW FULL TABLES");
    while ($row = $result->fetch_row()) {
        $name = $row[0];
        $type = $row[1];
        if ($type === 'VIEW') {
            $views[] = $name;
        } else {
            $tables[] = $name;
        }
    }

    $sqlScript = "SET FOREIGN_KEY_CHECKS=0;\n";

    // 1. Process TABLES (Structure + Data)
    foreach ($tables as $table) {
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get create table syntax
        $row = $mysqli_live->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sqlScript .= "\n" . $row[1] . ";\n\n";

        // Get data
        $result = $mysqli_live->query("SELECT * FROM `$table`");
        $columnCount = $result->field_count;

        while ($row = $result->fetch_row()) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                $row[$j] = $row[$j] === null ? "NULL" : "'" . $mysqli_live->real_escape_string($row[$j]) . "'";
                if (isset($row[$j])) {
                    $sqlScript .= $row[$j];
                } else {
                    $sqlScript .= "NULL";
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ",";
                }
            }
            $sqlScript .= ");\n";
        }
    }

    // 2. Process VIEWS (Structure Only)
    // Views must be created AFTER tables to avoid dependency errors
    foreach ($views as $view) {
        $sqlScript .= "DROP VIEW IF EXISTS `$view`;\n";
        
        // Get create view syntax
        $row = $mysqli_live->query("SHOW CREATE TABLE `$view`")->fetch_row();
        
        // Clean up Definer if needed (Optional, but good for portability)
        // $createSQL = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', 'DEFINER=CURRENT_USER', $row[1]);
        $sqlScript .= "\n" . $row[1] . ";\n\n";
    }

    $sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";
    $mysqli_live->close();

    echo "[OK] Data exported successfully! Size: " . round(strlen($sqlScript) / 1024 / 1024, 2) . " MB\n";

} catch (Exception $e) {
    die("\n[ERROR] Export failed: " . $e->getMessage() . "\n");
}

// 3. Connect to Local Server to Import Data
echo "\n[Step 2/3] Connecting to LOCAL XAMPP Server...\n";

$local_host = 'localhost';
$local_user = 'root';
$local_pass = ''; // Default XAMPP password is empty
// Check if using 'veeru' or 'veeru_db'
$local_name = 'veeru_db'; // Defaulting to veeru_db as per config/db.php

try {
    // Connect WITHOUT selecting database first
    $mysqli_local = new mysqli($local_host, $local_user, $local_pass);
    if ($mysqli_local->connect_error) {
        die("\n[ERROR] Could not connect to LOCAL server: " . $mysqli_local->connect_error . "\n");
    }
    echo "[OK] Connected to Local Server!\n";

    // Create Database if not exists
    echo "Creating database '$local_name' if needed...\n";
    $mysqli_local->query("CREATE DATABASE IF NOT EXISTS $local_name");
    $mysqli_local->select_db($local_name);

    // 4. Import Data
    echo "\n[Step 3/3] overwriting LOCAL database with LIVE data...\n";
    
    // Clear existing local tables first
    $mysqli_local->query("SET FOREIGN_KEY_CHECKS=0");
    $result = $mysqli_local->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $mysqli_local->query("DROP TABLE IF EXISTS " . $row[0]);
    }
    echo "Cleared old local tables...\n";

    // Execute the import
    if ($mysqli_local->multi_query($sqlScript)) {
        do {
            if ($result = $mysqli_local->store_result()) {
                $result->free();
            }
        } while ($mysqli_local->more_results() && $mysqli_local->next_result());
        
        echo "\n========================================\n";
        echo " [SUCCESS] LIVE DATA SYNCED TO LOCAL! \n";
        echo "========================================\n";
    } else {
        echo "\n[ERROR] Import failed: " . $mysqli_local->error . "\n";
    }

    $mysqli_local->close();

} catch (Exception $e) {
    die("\n[ERROR] Import failed: " . $e->getMessage() . "\n");
}

echo "\nPress Enter to exit...";
fgets(fopen("php://stdin", "r"));
?>
