<?php 
require_once 'c:/xampp/htdocs/veeru/backend/config/db.php'; 
$stmt = $pdo->query("SELECT * FROM pdf_study_folders"); 
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); 
?>
