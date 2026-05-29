<?php
$c = curl_init('https://plenty-phones-thank.loca.lt/veeru/api/teacher/upload_class_material.php');
curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($c, CURLOPT_HTTPHEADER, ['Bypass-Tunnel-Reminder: true']);
$res = curl_exec($c);
if ($res === false) {
    echo "cURL Error: " . curl_error($c) . "\n";
} else {
    echo "Response: $res\n";
    echo "HTTP Code: " . curl_getinfo($c, CURLINFO_HTTP_CODE) . "\n";
}
