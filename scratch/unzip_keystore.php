<?php
$zipFile = 'C:\Users\ADMIN\Downloads\@virajbansode__mcq-student-app-keystore-backup.zip';
$extractTo = __DIR__;

if (!file_exists($zipFile)) {
    die("Zip file not found.\n");
}

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    echo "Files inside zip:\n";
    for($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        echo "- $filename\n";
    }
    $zip->extractTo($extractTo);
    $zip->close();
    echo "Extracted successfully to $extractTo.\n";
} else {
    echo "Failed to open zip file.\n";
}
?>
