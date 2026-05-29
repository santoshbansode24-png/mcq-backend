<?php
require 'config/db.php';
$s = $pdo->query("DESCRIBE pdf_study_content");
print_r($s->fetchAll());
?>
