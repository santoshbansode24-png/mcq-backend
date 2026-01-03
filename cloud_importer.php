<?php
// Cloud Database Importer
function prompt($label) {
    echo $label;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

echo "========================================\n";
echo "   VEERU CLOUD IMPORTER (PHP Version)   \n";
echo "========================================\n\n";

echo "Please enter your Railway Credentials:\n";

$host = prompt("Paste Host (e.g. junction.proxy.rlwy.net): ");
$port = prompt("Paste Port (e.g. 56712): ");
$user = prompt("Paste User (default 'root'): ");
if (empty($user)) $user = 'root';
$pass = prompt("Paste Password: ");

$dbname = 'railway';

echo "\nConnecting to $host:$port...\n";

try {
    // 1. Connect
    $mysqli = new mysqli($host, $user, $pass, $dbname, (int)$port);

    if ($mysqli->connect_error) {
        die("\n[ERROR] Connection failed: " . $mysqli->connect_error . "\n");
    }

    echo "[OK] Connected successfully!\n";
    echo "Reading database file...\n";

    // 2. Read SQL
    $sqlFile = __DIR__ . '/production_db.sql';
    if (!file_exists($sqlFile)) {
        die("\n[ERROR] File not found: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);
    
    // 3. Import
    echo "Importing data (this may take a moment)...\n";
    
    // 3. Import
    echo "Importing data (this may take a moment)...\n";

    // Detect encoding and convert to UTF-8 if needed
    // PowerShell often outputs UTF-16LE
    if (substr($sql, 0, 2) === "\xFF\xFE") {
        echo "Detected UTF-16LE encoding (PowerShell format). Converting to UTF-8...\n";
        $sql = mb_convert_encoding($sql, 'UTF-8', 'UTF-16LE');
    }
    
    // Check for UTF-8 BOM (can exist natively or after conversion)
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        echo "Detected UTF-8 BOM. Removing...\n";
        $sql = substr($sql, 3);
    }

    // FIX: Remove DELIMITER commands (Not supported by mysqli)
    echo "Sanitizing SQL (Removing DELIMITER commands)...\n";
    $sql = preg_replace('/^DELIMITER\s+.*;\s*$/m', '', $sql); // Remove 'DELIMITER ;;' lines
    $sql = str_replace(';;', ';', $sql); // Convert ';;' to standard ';'

    // Execute multi-query (Native MySQL parsing)
    // We reverted to this because explode(';') breaks on semicolons inside strings.
    echo "Executing SQL statements...\n";
    
    if ($mysqli->multi_query($sql)) {
        $count = 0;
        do {
            $count++;
            // consume results
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
             if ($count % 50 == 0) echo ".";
        } while ($mysqli->more_results() && $mysqli->next_result());
        
        echo "\n========================================\n";
        echo " [SUCCESS] DATABASE IMPORTED! \n";
        echo "========================================\n";
    } else {
        echo "\n[ERROR] Import failed: " . $mysqli->error . "\n";
    }

    $mysqli->close();

} catch (Exception $e) {
    echo "\n[EXCEPTION] " . $e->getMessage() . "\n";
}

echo "\nPress Enter to exit...";
fgets(fopen("php://stdin", "r"));
?>
