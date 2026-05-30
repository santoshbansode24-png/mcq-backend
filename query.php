<?php
$c = new mysqli('yamanote.proxy.rlwy.net', 'root', 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf', 'railway', 24540);
$r = $c->query('SELECT * FROM transactions ORDER BY transaction_id DESC LIMIT 5');
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
