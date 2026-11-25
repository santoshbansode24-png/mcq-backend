<?php
/**
 * Admin AJAX Endpoint: Batch Translate MCQs or Flashcards to Marathi
 * Veeru
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/marathi_translator.php';

$type = strtolower(trim($_POST['type'] ?? 'mcqs'));
$chapterId = intval($_POST['chapter_id'] ?? 0);
$limit = min(15, max(1, intval($_POST['batch_size'] ?? 10)));
$boardName = $_SESSION['board_name'] ?? 'Maharashtra State Board';

if ($chapterId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid chapter.']);
    exit();
}

try {
    // Fetch chapter & subject context for accurate terminology
    $ctxStmt = $pdo->prepare("
        SELECT ch.chapter_name, s.subject_name
        FROM chapters ch
        JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE ch.chapter_id = ?
    ");
    $ctxStmt->execute([$chapterId]);
    $ctx = $ctxStmt->fetch(PDO::FETCH_ASSOC);
    $subjectName = $ctx['subject_name'] ?? '';
    $chapterName = $ctx['chapter_name'] ?? '';

    if ($type === 'flashcards') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM flashcards WHERE chapter_id = ? AND (question_front_mr IS NULL OR TRIM(question_front_mr) = '')");
        $countStmt->execute([$chapterId]);
        $remainingBefore = (int)$countStmt->fetchColumn();

        if ($remainingBefore === 0) {
            echo json_encode([
                'status' => 'success',
                'translated' => 0,
                'remaining' => 0,
                'message' => 'All flashcards in this chapter already have Marathi translations.'
            ]);
            exit();
        }

        $rowsStmt = $pdo->prepare("
            SELECT id, question_front, answer_back
            FROM flashcards
            WHERE chapter_id = ? AND (question_front_mr IS NULL OR TRIM(question_front_mr) = '')
            ORDER BY id ASC
            LIMIT {$limit}
        ");
        $rowsStmt->execute([$chapterId]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        $res = translateFlashcardsToMarathi($pdo, $rows, $subjectName, $chapterName, $boardName);

        $countStmt->execute([$chapterId]);
        $remainingAfter = (int)$countStmt->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'translated' => $res['translated'],
            'skipped' => $res['skipped'],
            'remaining' => $remainingAfter,
            'errors' => $res['errors'],
            'message' => "Translated {$res['translated']} flashcards. Remaining: {$remainingAfter}."
        ]);
        exit();
    }

    // Default: MCQs
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM mcqs WHERE chapter_id = ? AND (question_mr IS NULL OR TRIM(question_mr) = '')");
    $countStmt->execute([$chapterId]);
    $remainingBefore = (int)$countStmt->fetchColumn();

    if ($remainingBefore === 0) {
        echo json_encode([
            'status' => 'success',
            'translated' => 0,
            'remaining' => 0,
            'message' => 'All MCQs in this chapter already have Marathi translations.'
        ]);
        exit();
    }

    $rowsStmt = $pdo->prepare("
        SELECT mcq_id, question, option_a, option_b, option_c, option_d, explanation
        FROM mcqs
        WHERE chapter_id = ? AND (question_mr IS NULL OR TRIM(question_mr) = '')
        ORDER BY mcq_id ASC
        LIMIT {$limit}
    ");
    $rowsStmt->execute([$chapterId]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $res = translateMcqsToMarathi($pdo, $rows, $subjectName, $chapterName, $boardName);

    $countStmt->execute([$chapterId]);
    $remainingAfter = (int)$countStmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'translated' => $res['translated'],
        'skipped' => $res['skipped'],
        'remaining' => $remainingAfter,
        'errors' => $res['errors'],
        'message' => "Translated {$res['translated']} MCQs. Remaining: {$remainingAfter}."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
