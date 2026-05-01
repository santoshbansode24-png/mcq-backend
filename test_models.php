<?php
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=AIzaSyCvmd1s-1lkjyZ1g7QROmGhq_lv6kCvdFY');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
echo curl_exec($ch);
?>
