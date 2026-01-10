<?php
// backend/init_cloud_db.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Initializing Cloud Database...</h1>";

// 1. Get Credentials from Environment (or fail)
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
if ($db_pass === false) {
    $db_pass = getenv('DB_PASS');
}
// $db_pass = getenv('DB_PASSWORD'); // Note: Make sure Env var is DB_PASSWORD
$db_port = getenv('DB_PORT');

if (!$db_host || !$db_user || !$db_pass) {
    die("❌ Error: Missing Environment Variables. <br>Please set DB_HOST, DB_USER, DB_PASSWORD, DB_NAME in Render.");
}

echo "Attempting to connect to: <strong>$db_host</strong>...<br>";

// 2. Connect to Database
try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => true, // Required for TiDB
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✅ Connected to Database!<br>";
    
} catch (PDOException $e) {
    die("❌ Connection Failed: " . $e->getMessage());
}

// 3. Read structure from existing file or define it here
// We'll read the existing database.sql
$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("❌ Error: database.sql not found at $sqlFile");
}

$sql = file_get_contents($sqlFile);

// 4. Split and Execute Queries
// Remove comments to avoid issues
$lines = explode("\n", $sql);
$cleanSql = "";
foreach ($lines as $line) {
    $line = trim($line);
    if ($line && !str_starts_with($line, '--') && !str_starts_with($line, '#')) {
        $cleanSql .= $line . "\n";
    }
}

// Split by semicolon
$queries = explode(';', $cleanSql);

echo "Executing queries...<br>";
$count = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        try {
            $pdo->exec($query);
            $count++;
        } catch (PDOException $e) {
            echo "⚠️ Warning on query: " . htmlspecialchars(substr($query, 0, 50)) . "... <br>";
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<h2>✅ Success! Executed $count queries.</h2>";
echo "<p>Your database is now ready. You can open the App.</p>";
?>
