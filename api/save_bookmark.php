<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->user_id) && !empty($data->item_id) && !empty($data->type)) {
    $user_id = $data->user_id;
    $item_id = $data->item_id; // Could be video_id, note_id, or question_id
    $type = $data->type; // 'video', 'note', 'question'
    $title = $data->title;
    $meta = isset($data->meta) ? json_encode($data->meta) : null; // Extra info like subject name

    $query = "INSERT INTO bookmarks (user_id, item_id, type, title, meta) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $user_id, $item_id, $type, $title, $meta);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Bookmarked successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to bookmark']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete data']);
}
?>
