<?php
$res = file_get_contents('https://api.veeruapp.in/backend/api/debug_db_user.php?action=stats');
echo "Stats Response:\n" . $res . "\n";
?>
