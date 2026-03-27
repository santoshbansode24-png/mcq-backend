<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    $job_id = $_POST['job_id'] ?? 0;

    if (!$user_id || !$job_id) {
        die(json_encode(['status' => 'error', 'message' => 'Missing parameters']));
    }

    try {
        // Delete the job from database
        $stmt = $pdo->prepare("DELETE FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
        $stmt->execute([$job_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Job deleted']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
