<?php
$aabUrl = 'https://expo.dev/artifacts/eas/rqiq2vP1BX3ZY7hd_KwfVTnMCqhWDAiOgWB_p6nTGO0.aab';
$localFile = __DIR__ . '/student_app_temp.aab';

echo "Downloading AAB file...\n";
$ch = curl_init($aabUrl);
$fp = fopen($localFile, 'wb');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);
fclose($fp);

echo "AAB Downloaded. Size: " . filesize($localFile) . " bytes\n";

if (file_exists($localFile)) {
    echo "Running keytool to print AAB certificate info...\n";
    // For AAB we can extract the certificate or print signatures
    $cmd = "keytool -printcert -jarfile " . escapeshellarg($localFile);
    echo "CMD: $cmd\n";
    exec($cmd, $output, $return_val);
    echo "Output:\n" . implode("\n", $output) . "\n";
    
    // Clean up
    unlink($localFile);
}
?>
