<?php
$source = __DIR__ . '/upload_cert.pem';
$dest = 'C:\Users\ADMIN\.gemini\antigravity\brain\bbe2b035-ae9b-4ff7-9393-3e7e97ce3fd5\upload_cert.pem';

if (copy($source, $dest)) {
    echo "Successfully copied to $dest\n";
} else {
    echo "Failed to copy file.\n";
}
?>
