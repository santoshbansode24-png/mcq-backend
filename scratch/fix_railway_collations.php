<?php
$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "🚀 Aligning all Railway table collations to utf8mb4_unicode_ci...\n";

$tables = ['class_updates', 'notifications', 'users', 'messages', 'classrooms', 'student_class_mapping', 'live_exams', 'mcq_attempts'];

foreach ($tables as $t) {
    try {
        $pdo->exec("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Converted table '$t' to utf8mb4_unicode_ci\n";
    } catch (Exception $e) {
        echo "⚠️ Could not convert '$t': " . $e->getMessage() . "\n";
    }
}
echo "🎉 Collation alignment complete!\n";
?>
