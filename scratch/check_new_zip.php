<?php
$zipFile = 'C:\Users\ADMIN\Downloads\@virajbansode__mcq-student-app-keystore-backup (2).zip';
$extractTo = __DIR__ . '/new_keystore';

if (!file_exists($zipFile)) {
    die("Zip file not found.\n");
}

if (!is_dir($extractTo)) mkdir($extractTo);

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractTo);
    $zip->close();
    echo "Extracted new zip successfully.\n";
    
    // Read the md file
    $mdFile = $extractTo . '/@virajbansode__mcq-student-app-keystore-credentials.md';
    if (file_exists($mdFile)) {
        echo "Credentials file content:\n";
        echo file_get_contents($mdFile) . "\n";
    }
} else {
    echo "Failed to open zip file.\n";
}
?>
