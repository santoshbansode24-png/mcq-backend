<?php
$c=curl_init('https://plenty-phones-thank.loca.lt/veeru/api/get_classes.php?board=CBSE');
curl_setopt($c,CURLOPT_CUSTOMREQUEST,'OPTIONS');
curl_setopt($c,CURLOPT_RETURNTRANSFER,1);
curl_setopt($c,CURLOPT_HEADER,1);
echo curl_exec($c);
