<?php
$result = file_get_contents('https://api.veeruapp.in/api/update_railway_db.php?v=1');
echo "RESPONSE:\n" . $result . "\n";
?>
