<?php
$ch = curl_init('https://api.veeruapp.in/backend/api/run_r2_db_update.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Backend Endpoint Response:\n" . $res . "\n";
