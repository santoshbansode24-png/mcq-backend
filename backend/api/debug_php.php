<?php
echo "Current upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Current post_max_size: " . ini_get('post_max_size') . "<br>";
echo "Current memory_limit: " . ini_get('memory_limit') . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
?>
