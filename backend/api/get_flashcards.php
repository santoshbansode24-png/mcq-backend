<?php
// 1. Precise Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET'); // Restrict to GET only

require_once '../config/db.php';



try {
    // 2. Better Input Sanitization
    $chapter_id = filter_input(INPUT_GET, 'chapter_id', FILTER_VALIDATE_INT) ?: 0;
    $subject    = filter_input(INPUT_GET, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // 3. Dynamic Query Building
    $sql = "SELECT id, question_front, answer_back, chapter_id FROM flashcards"; // Selected specific columns matching DB schema
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

    // 4. Execution using Prepared Statements
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Flashcards fetched', $data ?: []);

} catch (PDOException $e) {
    // 5. Security: Log error internally, don't show structure to user
    error_log("Flashcard API Error: " . $e->getMessage());
    sendResponse('error', [], 'Internal Server Error', 500);
}