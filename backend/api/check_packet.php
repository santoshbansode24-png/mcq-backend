<?php
require_once '../config/db.php';
$stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
$res = $stmt->fetch();
echo json_encode(['max_allowed_packet' => $res['Value']]);
