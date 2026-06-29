<?php
$zipFile = 'C:\Users\ADMIN\Downloads\@virajbansode__mcq-student-app-keystore-backup (3).zip';
$extractTo = __DIR__ . '/keystore_three';

if (!file_exists($zipFile)) {
    die("Zip file not found.\n");
}

if (!is_dir($extractTo)) mkdir($extractTo);

$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractTo);
    $zip->close();
    echo "Extracted zip (3) successfully.\n";
    
    // Read the md file
    $files = scandir($extractTo);
    $mdFile = '';
    $jksFile = '';
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $mdFile = $extractTo . '/' . $file;
        }
        if (pathinfo($file, PATHINFO_EXTENSION) === 'jks') {
            $jksFile = $extractTo . '/' . $file;
        }
    }
    
    if ($mdFile) {
        echo "Credentials file ($mdFile):\n";
        $mdContent = file_get_contents($mdFile);
        echo $mdContent . "\n";
        
        // Parse credentials
        $storepass = '';
        $alias = '';
        $keypass = '';
        if (preg_match('/keystore password:\s*(\S+)/i', $mdContent, $m)) {
            $storepass = trim($m[1]);
        }
        if (preg_match('/key alias:\s*(\S+)/i', $mdContent, $m)) {
            $alias = trim($m[1]);
        }
        if (preg_match('/key password:\s*(\S+)/i', $mdContent, $m)) {
            $keypass = trim($m[1]);
        }
        
        echo "Parsed: Storepass: $storepass, Alias: $alias, Keypass: $keypass\n";
        
        if ($jksFile && $storepass && $alias) {
            echo "Running keytool to print fingerprint...\n";
            $cmd = "keytool -list -v -keystore " . escapeshellarg($jksFile) . " -storepass " . escapeshellarg($storepass) . " -alias " . escapeshellarg($alias);
            echo "CMD: $cmd\n";
            exec($cmd, $output, $return_var);
            echo "Output:\n" . implode("\n", $output) . "\n";
            
            // Also generate PEM file
            $pemFile = __DIR__ . '/upload_cert_three.pem';
            $pemCmd = "keytool -export -rfc -alias " . escapeshellarg($alias) . " -file " . escapeshellarg($pemFile) . " -keystore " . escapeshellarg($jksFile) . " -storepass " . escapeshellarg($storepass);
            echo "PEM CMD: $pemCmd\n";
            exec($pemCmd, $pemOutput, $pemReturn);
            if ($pemReturn === 0 && file_exists($pemFile)) {
                echo "PEM file generated successfully at $pemFile\n";
                echo "PEM Content:\n" . file_get_contents($pemFile) . "\n";
            } else {
                echo "Failed to generate PEM file. Return: $pemReturn\n" . implode("\n", $pemOutput) . "\n";
            }
        }
    }
} else {
    echo "Failed to open zip file.\n";
}
?>
