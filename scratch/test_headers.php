<?php
$ch = curl_init('https://api.veeruapp.in/check.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
echo "HEADERS:\n" . $res . "\n";
curl_close($ch);
?>
