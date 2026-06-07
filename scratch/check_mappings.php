<?php
$c = new PDO('mysql:host=yamanote.proxy.rlwy.net;port=24540;dbname=railway', 'root', 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf');
print_r($c->query('SELECT class_id, count(*) as count FROM student_class_mapping GROUP BY class_id')->fetchAll(PDO::FETCH_ASSOC));
?>
