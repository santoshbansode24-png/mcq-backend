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

    // Lazy Fallback (Safety Net): If Marathi is requested and some rows are untranslated, translate up to 10 on the fly
    if ($lang === 'mr' && ($_GET['auto_translate'] ?? '1') !== '0' && !empty($rows)) {
        $missingRows = array_values(array_filter($rows, function ($r) {
            return empty(trim((string)($r['question_front_mr'] ?? '')));
        }));
        if (!empty($missingRows) && file_exists(__DIR__ . '/../config/marathi_translator.php')) {
            try {
                require_once __DIR__ . '/../config/marathi_translator.php';
                $batch = array_slice($missingRows, 0, 10);
                $txResult = translateFlashcardsToMarathi($pdo, $batch);
                if (!empty($txResult['items'])) {
                    foreach ($rows as &$r) {
                        $id = (int)$r['id'];
                        if (isset($txResult['items'][$id])) {
                            $r = array_merge($r, $txResult['items'][$id]);
                        }
                    }
                    unset($r);
                }
            } catch (Throwable $txErr) {
                error_log('Lazy Marathi Flashcard translation fallback failed: ' . $txErr->getMessage());
            }
        }
    }

    $cards = [];
    foreach ($rows as $row) {
        $isTranslated = true;
        $card = [
            'id' => $row['id'],
            'chapter_id' => $row['chapter_id'],
            'subject' => $row['subject'] ?? '',
            'topic' => $row['topic'] ?? '',
            'question_front' => $row['question_front'],
            'answer_back' => $row['answer_back']
        ];

        if ($lang !== 'en') {
            $card['question_front_en'] = $row['question_front'];
            $card['answer_back_en'] = $row['answer_back'];
        }

        if ($lang === 'hi') {
            $isTranslated = !empty($row['question_front_hi']);
            if (!empty($row['question_front_hi'])) $card['question_front'] = $row['question_front_hi'];
            if (!empty($row['answer_back_hi'])) $card['answer_back'] = $row['answer_back_hi'];
        } elseif ($lang === 'mr') {
            $isTranslated = !empty($row['question_front_mr']);
            if (!empty($row['question_front_mr'])) $card['question_front'] = $row['question_front_mr'];
            if (!empty($row['answer_back_mr'])) $card['answer_back'] = $row['answer_back_mr'];
        }

        $card['is_translated'] = $isTranslated;
        $cards[] = $card;
    }

    sendResponse('success', 'Flashcards fetched', $cards);

} catch (PDOException $e) {
    error_log("Flashcard API Error: " . $e->getMessage());
    sendResponse('error', 'Internal Server Error', null, 500);
}