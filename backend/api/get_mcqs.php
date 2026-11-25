<?php
/**
 * Get MCQs API (Multi-Language Supported)
 * Veeru
 */
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/cors_middleware.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Only GET requests are allowed', null, 405);
}

$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$chapter_ids = isset($_GET['chapter_ids']) ? $_GET['chapter_ids'] : '';
$lang = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? 'en'));

if ($chapter_id <= 0 && empty($chapter_ids)) {
    sendResponse('error', 'Valid chapter_id or chapter_ids is required', null, 400);
}

try {
    if (!empty($chapter_ids)) {
        $ids_array = array_filter(array_map('intval', explode(',', $chapter_ids)));
        if (empty($ids_array)) {
            sendResponse('error', 'Invalid chapter_ids format', null, 400);
        }
        $inQuery = implode(',', array_fill(0, count($ids_array), '?'));
        
        $stmt = $pdo->prepare("
            SELECT * FROM mcqs
            WHERE chapter_id IN ($inQuery)
            ORDER BY mcq_id ASC
        ");
        $stmt->execute(array_values($ids_array));
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM mcqs
            WHERE chapter_id = ?
            ORDER BY mcq_id ASC
        ");
        $stmt->execute([$chapter_id]);
    }
    
    $rows = $stmt->fetchAll();

    // Lazy Fallback (Safety Net): If Marathi is requested and some rows are untranslated, translate up to 10 on the fly
    if ($lang === 'mr' && ($_GET['auto_translate'] ?? '1') !== '0' && !empty($rows)) {
        $missingRows = array_values(array_filter($rows, function ($r) {
            return empty(trim((string)($r['question_mr'] ?? '')));
        }));
        if (!empty($missingRows) && file_exists(__DIR__ . '/../config/marathi_translator.php')) {
            try {
                require_once __DIR__ . '/../config/marathi_translator.php';
                $batch = array_slice($missingRows, 0, 10);
                $txResult = translateMcqsToMarathi($pdo, $batch);
                if (!empty($txResult['items'])) {
                    foreach ($rows as &$r) {
                        $id = (int)$r['mcq_id'];
                        if (isset($txResult['items'][$id])) {
                            $r = array_merge($r, $txResult['items'][$id]);
                        }
                    }
                    unset($r);
                }
            } catch (Throwable $txErr) {
                error_log('Lazy Marathi MCQ translation fallback failed: ' . $txErr->getMessage());
            }
        }
    }
    
    $mcqs = [];
    foreach ($rows as $row) {
        $isTranslated = true;
        $mcq = [
            'mcq_id' => $row['mcq_id'],
            'chapter_id' => $row['chapter_id'],
            'question' => $row['question'],
            'option_a' => $row['option_a'],
            'option_b' => $row['option_b'],
            'option_c' => $row['option_c'],
            'option_d' => $row['option_d'],
            'correct_answer' => $row['correct_answer'],
            'explanation' => $row['explanation'],
            'difficulty' => $row['difficulty']
        ];

        if ($lang !== 'en') {
            $mcq['question_en'] = $row['question'];
            $mcq['option_a_en'] = $row['option_a'];
            $mcq['option_b_en'] = $row['option_b'];
            $mcq['option_c_en'] = $row['option_c'];
            $mcq['option_d_en'] = $row['option_d'];
            $mcq['explanation_en'] = $row['explanation'];
        }

        if ($lang === 'hi') {
            $isTranslated = !empty($row['question_hi']);
            if (!empty($row['question_hi'])) $mcq['question'] = $row['question_hi'];
            if (!empty($row['option_a_hi'])) $mcq['option_a'] = $row['option_a_hi'];
            if (!empty($row['option_b_hi'])) $mcq['option_b'] = $row['option_b_hi'];
            if (!empty($row['option_c_hi'])) $mcq['option_c'] = $row['option_c_hi'];
            if (!empty($row['option_d_hi'])) $mcq['option_d'] = $row['option_d_hi'];
            if (!empty($row['explanation_hi'])) $mcq['explanation'] = $row['explanation_hi'];
        } elseif ($lang === 'mr') {
            $isTranslated = !empty($row['question_mr']);
            if (!empty($row['question_mr'])) $mcq['question'] = $row['question_mr'];
            if (!empty($row['option_a_mr'])) $mcq['option_a'] = $row['option_a_mr'];
            if (!empty($row['option_b_mr'])) $mcq['option_b'] = $row['option_b_mr'];
            if (!empty($row['option_c_mr'])) $mcq['option_c'] = $row['option_c_mr'];
            if (!empty($row['option_d_mr'])) $mcq['option_d'] = $row['option_d_mr'];
            if (!empty($row['explanation_mr'])) $mcq['explanation'] = $row['explanation_mr'];
        }

        $mcq['is_translated'] = $isTranslated;
        $mcqs[] = $mcq;
    }
    
    sendResponse('success', 'MCQs retrieved successfully', $mcqs, 200);
    
} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>