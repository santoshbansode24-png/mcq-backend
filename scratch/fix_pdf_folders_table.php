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

echo "1. Ensuring table 'pdf_folders' exists on Railway...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS `pdf_folders` (
  `folder_id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✅ Table 'pdf_folders' created/ensured!\n";

echo "\n2. Checking Vocab Words count per set in Railway:\n";
$sets = $pdo->query("SELECT set_number, COUNT(*) as cnt FROM vocab_words GROUP BY set_number ORDER BY set_number ASC")->fetchAll();
foreach ($sets as $s) {
    echo "   Set {$s['set_number']}: {$s['cnt']} words\n";
}
?>
