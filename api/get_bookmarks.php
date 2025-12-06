<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : die();

$query = "SELECT * FROM bookmarks WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$bookmarks = [];
while ($row = $result->fetch_assoc()) {
    $row['meta'] = json_decode($row['meta'], true);
    $bookmarks[] = $row;
}

echo json_encode(['status' => 'success', 'data' => $bookmarks]);
?>
