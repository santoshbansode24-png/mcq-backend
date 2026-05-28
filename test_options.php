<?php
$ch = curl_init('https://api.veeruapp.in/api/get_mcqs.php?chapter_ids=15,77,78,79');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $httpcode\n";
echo substr($response, 0, 500) . "\n";
?>
