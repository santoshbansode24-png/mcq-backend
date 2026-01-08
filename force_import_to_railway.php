<?php
// force_import_to_railway.php
// v3: Added "Smart Parsing" for Triggers, Procedures, and DELIMITERs
// Also strips "DEFINER" to avoid permission errors on the cloud.

set_time_limit(600); 
ini_set('memory_limit', '512M');

// CREDENTIALS
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

// Disable buffering to show progress in real-time
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

function flush_buffers() {
    echo(str_repeat(' ', 4096)); // Force flush padding
    if (ob_get_length()) { @ob_flush(); }
    @flush();
}echo "<h1>🚀 Veeru Database Syncer v5 (Auto-Export)</h1>";
flush_buffers();

// 0. AUTO-EXPORT LOCAL DATABASE
echo "<p>📦 Exporting local database (veeru_db)...</p>";
flush_buffers();

$dumpCommand = 'c:\\xampp\\mysql\\bin\\mysqldump -u root --default-character-set=utf8mb4 veeru_db > ' . __DIR__ . '\\railway_export.sql';
exec($dumpCommand, $output, $returnVar);

if ($returnVar !== 0) {
    die("<h2 style='color:red'>❌ Export Failed! Error code: $returnVar</h2>");
}
echo "<p style='color:green'>✅ Export successful!</p>";
flush_buffers();

echo "<p>⏳ Connecting to Railway...</p>";
flush_buffers();
$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("<h2 style='color:red'>❌ Connection Failed: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8mb4"); // FIX: Ensure Marathi/Hindi chars are not corrupted
echo "<p style='color:green'>✅ Connected!</p>";

$file_path = __DIR__ . '/railway_export.sql';
if (!file_exists($file_path)) {
    die("❌ File not found.");
}

$sql_content = file_get_contents($file_path);

// FIX 1: Encoding
if (substr($sql_content, 0, 2) === "\xFF\xFE") {
    $sql_content = mb_convert_encoding($sql_content, 'UTF-8', 'UTF-16LE');
}
// FIX 2: Remove BOM
$bom = pack('H*','EFBBBF');
if (substr($sql_content, 0, 3) === $bom) {
    $sql_content = substr($sql_content, 3);
}

// FIX 3: Remove DEFINER clauses (causes errors on cloud DBs)
// Regex to remove DEFINER=`root`@`localhost` or similar
$sql_content = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $sql_content);

echo "<p>🔧 Parsing SQL statements (handling DELIMITER)...</p>";

// Custom Splitter for DELIMITER support
$queries = [];
$delimiter = ';';
$lines = explode("\n", $sql_content);
$buffer = '';

foreach ($lines as $line) {
    $trimLine = trim($line);
    
    // Skip comments
    if (strpos($trimLine, '--') === 0 || strpos($trimLine, '/*') === 0 && strpos($trimLine, '/*!') !== 0) {
        continue;
    }
    
    // Handle DELIMITER command
    if (preg_match('/^DELIMITER\s+(\S+)/i', $trimLine, $matches)) {
        $delimiter = $matches[1];
        continue; // Don't add the DELIMITER line to the buffer
    }

    // Add line to buffer
    $buffer .= $line . "\n";

    // Check if query ends with the current delimiter
    // We check the trimmed line to see if it ends with the delimiter
    if (substr($trimLine, -strlen($delimiter)) === $delimiter) {
        // Remove delimiter from the end of the buffer string for execution
        // (mysqli doesn't want the delimiter in the query string usually, except ;)
        // Actually, for normal queries ; is fine, but for ;; it might fail if passed.
        // Let's rely on stripping the delimiter from the *text* query sent to DB.
        
        $sql_to_run = substr(trim($buffer), 0, -strlen($delimiter));
        
        if (!empty(trim($sql_to_run))) {
            $queries[] = $sql_to_run;
        }
        $buffer = '';
    }
}

// If anything remains in buffer
if (!empty(trim($buffer))) {
    $queries[] = trim($buffer);
}

echo "<p>⚡ Executing " . count($queries) . " queries (this may take 1-2 minutes)...</p>";

$count = 0;
foreach ($queries as $query) {
    if (!$conn->query($query)) {
        // Warning only, don't die (some drops might fail)
        echo "<small style='color:orange'>Warning: " . $conn->error . "</small><br>";
    }
    $count++;
    if ($count % 5 == 0) {
        echo "<span>.</span>";
        flush_buffers();
    }
}

echo "<h1 style='color:green'>🎉 SUCCESS!</h1>";
echo "<p>Executed $count queries. Database synced.</p>";

$conn->close();
?>
