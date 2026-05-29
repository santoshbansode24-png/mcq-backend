<?php
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, 'http://10.252.83.239/veeru/api/teacher/upload_class_material.php'); 
curl_setopt($ch, CURLOPT_POST, 1); 
curl_setopt($ch, CURLOPT_POSTFIELDS, ['teacher_id'=>1, 'class_id'=>1, 'title'=>'t', 'update_type'=>'worksheet']); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
$res = curl_exec($ch); 
echo "Response: $res\n"; 
echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
