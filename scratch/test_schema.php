<?php
require 'config/db.php';
echo "--- student_progress indexes ---\n";
print_r($pdo->query("SHOW INDEX FROM student_progress")->fetchAll(PDO::FETCH_ASSOC));
