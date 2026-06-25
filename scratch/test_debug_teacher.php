<?php
$res = file_get_contents('https://api.veeruapp.in/backend/api/debug_teacher.php?email=santoshbansode24@gmail.com');
echo "RESPONSE:\n" . strip_tags($res) . "\n";
?>
