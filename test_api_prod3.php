<?php
$result = file_get_contents('https://api.veeruapp.in/api/teacher/get_classes.php');
echo "RESPONSE:\n" . $result . "\n";
?>
