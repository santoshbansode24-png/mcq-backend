<?php
$c=curl_init('http://localhost/veeru/api/chat/send_message.php'); 
curl_setopt($c, CURLOPT_POST, 1); 
curl_setopt($c, CURLOPT_POSTFIELDS, json_encode(['sender_id'=>32, 'class_code'=>'TEST1', 'message_text'=>'hello'])); 
curl_setopt($c, CURLOPT_RETURNTRANSFER, true); 
echo curl_exec($c);
?>
