<?php
// Master Debug: Check ALL progress tables for a user
require_once '../config/db.php';
header('Content-Type: application/json');

$uid = isset($_GET['user_id']) ? intval($_GET['user_id']) : 35;

// User info
$stmt = $pdo->prepare("SELECT user_id, name, mobile, class_id FROM users WHERE user_id = ? OR mobile = ?");
$stmt->execute([$uid, $uid]);
$user = $stmt->fetch();
$realUid = $user['user_id'] ?? $uid;
$classId  = $user['class_id'] ?? null;

// content_progress (MCQ/flashcard set completion - what app writes)
$stmt1 = $pdo->prepare("
    SELECT cp.chapter_id, ch.chapter_name, cp.content_type, cp.status, cp.score, cp.total
    FROM content_progress cp
    JOIN chapters ch ON cp.chapter_id = ch.chapter_id
    WHERE cp.user_id = ?
    ORDER BY cp.updated_at DESC LIMIT 30
");
$stmt1->execute([$realUid]);
$contentProgress = $stmt1->fetchAll();

// Completed chapters from content_progress
$stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT chapter_id) as done FROM content_progress WHERE user_id = ? AND status = 'completed'");
$stmt2->execute([$realUid]);
$completedFromCP = $stmt2->fetch()['done'] ?? 0;

// mcq_attempts
$stmt3 = $pdo->prepare("SELECT COUNT(DISTINCT chapter_id) as chapters, COUNT(*) as total FROM mcq_attempts WHERE user_id = ?");
$stmt3->execute([$realUid]);
$mcqAttempts = $stmt3->fetch();

// student_progress
$stmt4 = $pdo->prepare("SELECT COUNT(DISTINCT chapter_id) as chapters, COUNT(*) as total FROM student_progress WHERE user_id = ?");
$stmt4->execute([$realUid]);
$studentProgress = $stmt4->fetch();

// Total chapters in class
$totalChapters = 0;
if ($classId) {
    $stmt5 = $pdo->prepare("SELECT COUNT(DISTINCT ch.chapter_id) as total FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id WHERE s.class_id = ?");
    $stmt5->execute([$classId]);
    $totalChapters = $stmt5->fetch()['total'] ?? 0;
}

echo json_encode([
    'deploy_marker'      => 'v5-content_progress-fix',
    'queried_uid'        => $uid,
    'user'               => $user,
    'total_chapters'     => $totalChapters,
    'content_progress'   => ['completed_chapters' => $completedFromCP, 'entries_count' => count($contentProgress), 'entries' => $contentProgress],
    'mcq_attempts'       => $mcqAttempts,
    'student_progress'   => $studentProgress,
], JSON_PRETTY_PRINT);
?>
