<?php
$c=curl_init('http://localhost/veeru/api/chat/get_messages.php?class_code=TEST1&user_id=32'); 
curl_setopt($c, CURLOPT_RETURNTRANSFER, true); 
echo curl_exec($c);
?>
