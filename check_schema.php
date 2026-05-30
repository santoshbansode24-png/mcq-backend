<?php
$c = new mysqli('yamanote.proxy.rlwy.net', 'root', 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf', 'railway', 24540);
$r = $c->query('DESCRIBE transactions');
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
