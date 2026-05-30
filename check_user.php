<?php
$c = new mysqli('yamanote.proxy.rlwy.net', 'root', 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf', 'railway', 24540);
$r = $c->query('SELECT subscription_status, subscription_expiry FROM users WHERE user_id = 8');
print_r($r->fetch_assoc());
?>
