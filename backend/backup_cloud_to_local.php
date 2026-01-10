<?php
// backend/backup_cloud_to_local.php

$MYSQLDUUMP_PATH = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$MYSQL_PATH = 'C:\\xampp\\mysql\\bin\\mysql.exe';

$LOCAL_DB_HOST = '127.0.0.1';
$LOCAL_DB_USER = 'root';
$LOCAL_DB_PASS = '';
$LOCAL_DB_NAME = 'veeru_db';

// Railway Credentials (Updated from force_import)
$REMOTE_HOST = 'yamanote.proxy.rlwy.net';
$REMOTE_USER = 'root';
$REMOTE_PASS = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$REMOTE_PORT = 24540;
$REMOTE_DB   = 'railway';

echo "<h1>🚀 Veeru Cloud-to-Local Backup Tool</h1>";

// 1. Snapshot Local DB (Safety Backup)
$timestamp = date('Y-m-d_H-i-s');
$local_backup_file = __DIR__ . "/backups/local_backup_$timestamp.sql";
if (!is_dir(__DIR__ . '/backups')) mkdir(__DIR__ . '/backups');

echo "<p>📦 Creating safety backup of Local DB...</p>";
$cmd_backup = "\"$MYSQLDUUMP_PATH\" -h $LOCAL_DB_HOST -u $LOCAL_DB_USER $LOCAL_DB_NAME > \"$local_backup_file\"";
exec($cmd_backup, $output, $return_var);

if ($return_var === 0) {
    echo "<p style='color:green'>✅ Local backup saved to: " . basename($local_backup_file) . "</p>";
} else {
    echo "<p style='color:red'>❌ Local backup failed! Stopping.</p>";
    exit();
}

// 2. Export Railway DB
$remote_export_file = __DIR__ . "/backups/railway_export_$timestamp.sql";
echo "<p>☁️  Downloading data from Railway (this may take a minute)...</p>";

// Note: --column-statistics=0 removed for compatibility with older XAMPP/MariaDB versions
$cmd_export = "\"$MYSQLDUUMP_PATH\" -h $REMOTE_HOST -P $REMOTE_PORT -u $REMOTE_USER -p$REMOTE_PASS $REMOTE_DB > \"$remote_export_file\"";

// Hide password in echo
echo "<!-- Command: " . str_replace($REMOTE_PASS, '*****', $cmd_export) . " -->";

exec($cmd_export, $output_remote, $return_var_remote);

if ($return_var_remote === 0 && filesize($remote_export_file) > 1000) {
    echo "<p style='color:green'>✅ Cloud data downloaded successfully!</p>";
} else {
    echo "<p style='color:red'>❌ Cloud download failed! (Size: " . (filesize($remote_export_file) ?? 0) . " bytes)</p>";
    if (file_exists($remote_export_file)) {
        echo "<pre>" . file_get_contents($remote_export_file, false, null, 0, 500) . "</pre>";
    }
    exit();
}

// 3. Import to Local DB
echo "<p>🔄 Updating Local Database...</p>";
$cmd_import = "\"$MYSQL_PATH\" -h $LOCAL_DB_HOST -u $LOCAL_DB_USER $LOCAL_DB_NAME < \"$remote_export_file\"";
exec($cmd_import, $output_import, $return_var_import);

if ($return_var_import === 0) {
    echo "<h2 style='color:green'>🎉 SUCCESS! Local Database is now in sync with Railway.</h2>";
    echo "<p>Your PC now has the latest 1000+ files from the server.</p>";
} else {
    echo "<p style='color:red'>❌ Import failed!</p>";
}
?>
