<?php
require 'backend/config/db.php';
print_r($pdo->query('SHOW CREATE TABLE pdf_study_jobs')->fetchAll(PDO::FETCH_ASSOC));
?>
