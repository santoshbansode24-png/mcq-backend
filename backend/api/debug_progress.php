<?php
// Debug: Check student_progress for user 35
require_once '../config/db.php';
header('Content-Type: application/json');

$uid = isset($_GET['user_id']) ? intval($_GET['user_id']) : 35;

// Check student_progress
$stmt = $pdo->prepare("
    SELECT sp.chapter_id, ch.chapter_name, sp.mcq_score, sp.total_mcq, sp.percentage, sp.completed_at
    FROM student_progress sp
    JOIN chapters ch ON sp.chapter_id = ch.chapter_id
    WHERE sp.user_id = ?
    ORDER BY sp.completed_at DESC
    LIMIT 20
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

// Check user class
$stmt2 = $pdo->prepare("SELECT user_id, name, class_id FROM users WHERE user_id = ?");
$stmt2->execute([$uid]);
$user = $stmt2->fetch();

// Total chapters in user class
$stmt3 = $pdo->prepare("
    SELECT COUNT(DISTINCT ch.chapter_id) as total
    FROM chapters ch JOIN subjects s ON ch.subject_id = s.subject_id
    WHERE s.class_id = ?
");
$stmt3->execute([$user['class_id'] ?? 0]);
$totalChapters = $stmt3->fetch()['total'] ?? 0;

echo json_encode([
    'user' => $user,
    'total_chapters_in_class' => $totalChapters,
    'student_progress_count' => count($rows),
    'entries' => $rows,
    'commit' => '02a06bd-student_progress-fix',
], JSON_PRETTY_PRINT);
?>
