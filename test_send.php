<?php
$payload = json_encode(['sender_id'=>1,'class_code'=>'TEST12','message_text'=>'Hello Broadcast','receiver_id'=>null]);
$ch = curl_init('http://localhost/veeru/api/chat/send_message.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo curl_exec($ch);
?>
