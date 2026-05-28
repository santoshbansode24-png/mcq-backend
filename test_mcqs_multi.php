<?php
$ch = curl_init('http://localhost/veeru/api/get_mcqs.php?chapter_ids=146,147');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $httpcode\n";
echo substr($response, 0, 500) . "...\n";
?>
