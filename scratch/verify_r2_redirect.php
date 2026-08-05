<?php
$url = "https://api.veeruapp.in/api/serve_pdf.php?file=" . urlencode("uploads/notes/1771612157_The_First_Game_Changers__1__compressed__1_.pdf");

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response headers/body excerpt:\n" . substr($res, 0, 500) . "\n";
