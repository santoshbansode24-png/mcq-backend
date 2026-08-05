<?php
require_once __DIR__ . '/../backend/config/aws-config.php';

// Ensure secrets are loaded
if (file_exists(__DIR__ . '/../backend/config/secrets.php')) {
    require_once __DIR__ . '/../backend/config/secrets.php';
}

$url = "http://api.veeruapp.in/api/serve_pdf.php?file=" . urlencode("uploads/notes/1771612157_The_First_Game_Changers__1__compressed__1_.pdf");

echo "Testing fetch from: $url\n";
$tempFile = __DIR__ . '/temp_test.pdf';
$fp = fopen($tempFile, 'w+');
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

echo "HTTP Code: $httpCode | Size: " . filesize($tempFile) . " bytes\n";
if (filesize($tempFile) > 1000) {
    echo "File downloaded successfully!\n";
    $s3Url = uploadToS3($tempFile, "notes/1771612157_The_First_Game_Changers__1__compressed__1_.pdf");
    echo "R2 Upload Result: " . ($s3Url ? $s3Url : "FAILED") . "\n";
}
@unlink($tempFile);
