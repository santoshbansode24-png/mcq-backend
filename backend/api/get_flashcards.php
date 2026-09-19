<?php
/**
 * Get Flashcards API (Multi-Language Supported)
 * Veeru
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';

try {
    $chapter_id = filter_input(INPUT_GET, 'chapter_id', FILTER_VALIDATE_INT) ?: 0;
    $subject    = filter_input(INPUT_GET, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $lang       = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? 'en'));
    
    $sql = "SELECT * FROM flashcards";
    $where = [];
    $params = [];

    if ($chapter_id > 0) {
        $where[] = "chapter_id = ?";
        $params[] = $chapter_id;
    } elseif (!empty($subject)) {
        $where[] = "subject = ?";
        $params[] = $subject;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cards = [];
    foreach ($rows as $row) {
        $card = [
            'id' => $row['id'],
            'chapter_id' => $row['chapter_id'],
            'subject' => $row['subject'] ?? '',
            'topic' => $row['topic'] ?? '',
            'question_front' => $row['question_front'],
            'answer_back' => $row['answer_back']
        ];

        if ($lang === 'hi') {
            if (!empty($row['question_front_hi'])) $card['question_front'] = $row['question_front_hi'];
            if (!empty($row['answer_back_hi'])) $card['answer_back'] = $row['answer_back_hi'];
        } elseif ($lang === 'mr') {
            if (!empty($row['question_front_mr'])) $card['question_front'] = $row['question_front_mr'];
            if (!empty($row['answer_back_mr'])) $card['answer_back'] = $row['answer_back_mr'];
        }

        $cards[] = $card;
    }

    sendResponse('success', 'Flashcards fetched', $cards);

} catch (PDOException $e) {
    error_log("Flashcard API Error: " . $e->getMessage());
    sendResponse('error', 'Internal Server Error', null, 500);
}