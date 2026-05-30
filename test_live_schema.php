<?php
$c=curl_init('https://api.veeruapp.in/api/update_schema.php'); 
curl_setopt($c, CURLOPT_RETURNTRANSFER, true); 
echo curl_exec($c);
?>
