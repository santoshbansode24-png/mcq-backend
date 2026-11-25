<?php
/**
 * Marathi Translation Helper (Gemini-powered, Textbook-Grade)
 * Translates MCQs and Flashcards as complete contextual units into Marathi (_mr columns)
 * with strict validation and formula/symbol protection.
 * Veeru
 */

require_once __DIR__ . '/ai_config.php';

/**
 * Build the strict Balbharati/Maharashtra State Board Marathi translation prompt for MCQs.
 */
function buildMcqMarathiPrompt(
    array $mcqs,
    string $subjectName = '',
    string $chapterName = '',
    string $boardName = 'Maharashtra State Board'
): string {
    $payloadItems = [];
    foreach ($mcqs as $row) {
        $payloadItems[] = [
            'mcq_id'      => (int)$row['mcq_id'],
            'question'    => (string)($row['question'] ?? ''),
            'option_a'    => (string)($row['option_a'] ?? ''),
            'option_b'    => (string)($row['option_b'] ?? ''),
            'option_c'    => (string)($row['option_c'] ?? ''),
            'option_d'    => (string)($row['option_d'] ?? ''),
            'explanation' => (string)($row['explanation'] ?? ''),
        ];
    }

    $contextLines = [];
    if ($boardName !== '') $contextLines[] = "Board: {$boardName}";
    if ($subjectName !== '') $contextLines[] = "Subject: {$subjectName}";
    if ($chapterName !== '') $contextLines[] = "Chapter: {$chapterName}";
    $contextBlock = !empty($contextLines) ? implode("\n", $contextLines) . "\n" : '';

    $itemsJson = json_encode($payloadItems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return <<<PROMPT
You are an expert Maharashtra State Board (Balbharati) textbook translator and subject-matter educator.
{$contextBlock}
Task: Translate each MCQ below from English into natural, grammatically accurate Marathi for Marathi-medium school students.

STRICT RULES:
1. Translate each MCQ as ONE complete contextual unit (question + option_a..option_d + explanation together) so that gender, number, case endings, and phrasing in the options match the question naturally in Marathi.
2. Use standard Maharashtra State Board / Balbharati textbook terminology (e.g., "प्रकाशसंश्लेषण" for Photosynthesis, "गुरुत्वाकर्षण" for Gravity, "पेशी" for Cell).
3. Keep unavoidable or key scientific/technical English terms in brackets alongside the Marathi term when helpful for students (e.g., "प्रकाशसंश्लेषण (Photosynthesis) म्हणजे काय?", "मायटोकॉन्ड्रिया (Mitochondria)").
4. PROTECT FORMULAS & SYMBOLS: NEVER alter numbers, LaTeX math expressions, chemical formulas (e.g., H₂O, CO₂, H2SO4), SI units (e.g., m/s², kg, cm), variables (x, y), or punctuation/option structure.
5. Keep all 4 translated options (option_a_mr, option_b_mr, option_c_mr, option_d_mr) distinct — never collapse two different English options into the same Marathi string.
6. If an English explanation is empty, return an empty string "" for explanation_mr.
7. If Subject is English Grammar, keep the quoted English sentence/word under test in English and write the Marathi question/explanation around it.
8. Return ONLY valid JSON matching this exact schema (no markdown fences, no commentary):
{
  "translations": [
    {
      "mcq_id": 123,
      "question_mr": "...",
      "option_a_mr": "...",
      "option_b_mr": "...",
      "option_c_mr": "...",
      "option_d_mr": "...",
      "explanation_mr": "..."
    }
  ]
}

INPUT MCQs:
{$itemsJson}
PROMPT;
}

/**
 * Build the strict Balbharati/Maharashtra State Board Marathi translation prompt for Flashcards.
 */
function buildFlashcardMarathiPrompt(
    array $cards,
    string $subjectName = '',
    string $chapterName = '',
    string $boardName = 'Maharashtra State Board'
): string {
    $payloadItems = [];
    foreach ($cards as $row) {
        $payloadItems[] = [
            'id'             => (int)$row['id'],
            'question_front' => (string)($row['question_front'] ?? ''),
            'answer_back'    => (string)($row['answer_back'] ?? ''),
        ];
    }

    $contextLines = [];
    if ($boardName !== '') $contextLines[] = "Board: {$boardName}";
    if ($subjectName !== '') $contextLines[] = "Subject: {$subjectName}";
    if ($chapterName !== '') $contextLines[] = "Chapter: {$chapterName}";
    $contextBlock = !empty($contextLines) ? implode("\n", $contextLines) . "\n" : '';

    $itemsJson = json_encode($payloadItems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return <<<PROMPT
You are an expert Maharashtra State Board (Balbharati) textbook translator and educator.
{$contextBlock}
Task: Translate each Flashcard (front question/term + back answer/definition) from English into clear, accurate Marathi for Marathi-medium students.

STRICT RULES:
1. Translate question_front and answer_back together as one unit so terminology and grammar match.
2. Use standard Balbharati/textbook Marathi terminology, keeping key English technical terms in brackets where helpful (e.g., "प्रकाशसंश्लेषण (Photosynthesis)").
3. PROTECT FORMULAS & SYMBOLS: NEVER alter numbers, LaTeX, chemical formulas (H₂O, CO₂), or units (m/s², kg).
4. Keep answers concise and student-friendly for quick flashcard revision.
5. Return ONLY valid JSON matching this exact schema (no markdown fences, no commentary):
{
  "translations": [
    {
      "id": 123,
      "question_front_mr": "...",
      "answer_back_mr": "..."
    }
  ]
}

INPUT FLASHCARDS:
{$itemsJson}
PROMPT;
}

/**
 * Decode JSON safely from Gemini response (handles accidental markdown fences).
 */
function decodeGeminiJsonResponse(string $raw): array {
    $parsed = json_decode($raw, true);
    if (is_array($parsed) && isset($parsed['translations'])) {
        return $parsed;
    }
    if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
        $parsed = json_decode($matches[0], true);
        if (is_array($parsed) && isset($parsed['translations'])) {
            return $parsed;
        }
    }
    throw new Exception('AI returned invalid JSON for Marathi translation.');
}

/**
 * Translate a batch of MCQ rows into Marathi, validate each item, and save to DB.
 * Returns ['translated' => int, 'skipped' => int, 'errors' => array, 'items' => array (id => row)]
 */
function translateMcqsToMarathi(
    PDO $pdo,
    array $mcqRows,
    string $subjectName = '',
    string $chapterName = '',
    string $boardName = 'Maharashtra State Board'
): array {
    if (empty($mcqRows)) {
        return ['translated' => 0, 'skipped' => 0, 'errors' => [], 'items' => []];
    }
    if (!function_exists('callGeminiAPI')) {
        throw new Exception("Gemini helper 'callGeminiAPI' is not available.");
    }

    $byId = [];
    foreach ($mcqRows as $r) {
        $byId[(int)$r['mcq_id']] = $r;
    }

    $prompt = buildMcqMarathiPrompt($mcqRows, $subjectName, $chapterName, $boardName);
    $aiRaw = callGeminiAPI($prompt, [
        'temperature'      => 0.2,
        'maxOutputTokens'  => 8192,
        'responseMimeType' => 'application/json',
    ]);

    $parsed = decodeGeminiJsonResponse($aiRaw);
    $translations = $parsed['translations'] ?? [];
    if (!is_array($translations)) {
        throw new Exception('Invalid translations array returned by AI.');
    }

    $updateStmt = $pdo->prepare("
        UPDATE mcqs
        SET question_mr = ?, option_a_mr = ?, option_b_mr = ?, option_c_mr = ?, option_d_mr = ?, explanation_mr = ?
        WHERE mcq_id = ?
    ");

    $translated = 0;
    $skipped = 0;
    $errors = [];
    $savedItems = [];

    foreach ($translations as $item) {
        $id = (int)($item['mcq_id'] ?? 0);
        if ($id <= 0 || !isset($byId[$id])) {
            $skipped++;
            continue;
        }

        $qMr = trim((string)($item['question_mr'] ?? ''));
        $aMr = trim((string)($item['option_a_mr'] ?? ''));
        $bMr = trim((string)($item['option_b_mr'] ?? ''));
        $cMr = trim((string)($item['option_c_mr'] ?? ''));
        $dMr = trim((string)($item['option_d_mr'] ?? ''));
        $eMr = trim((string)($item['explanation_mr'] ?? ''));

        // Validation 1: Required fields non-empty
        if ($qMr === '' || $aMr === '' || $bMr === '' || $cMr === '' || $dMr === '') {
            $skipped++;
            $errors[] = "MCQ #{$id}: empty question or option after translation.";
            continue;
        }

        // Validation 2: Distinct options check (if source English options were distinct)
        $orig = $byId[$id];
        $origOpts = array_map(fn($k) => mb_strtolower(trim((string)($orig[$k] ?? ''))), ['option_a', 'option_b', 'option_c', 'option_d']);
        $mrOpts   = array_map(fn($v) => mb_strtolower(trim($v)), [$aMr, $bMr, $cMr, $dMr]);
        if (count(array_unique($origOpts)) === 4 && count(array_unique($mrOpts)) < 4) {
            $skipped++;
            $errors[] = "MCQ #{$id}: two or more options became identical after translation.";
            continue;
        }

        $updateStmt->execute([$qMr, $aMr, $bMr, $cMr, $dMr, $eMr, $id]);
        $translated++;
        $savedItems[$id] = [
            'question_mr'    => $qMr,
            'option_a_mr'    => $aMr,
            'option_b_mr'    => $bMr,
            'option_c_mr'    => $cMr,
            'option_d_mr'    => $dMr,
            'explanation_mr' => $eMr,
        ];
    }

    return [
        'translated' => $translated,
        'skipped'    => $skipped,
        'errors'     => $errors,
        'items'      => $savedItems,
    ];
}

/**
 * Translate a batch of Flashcard rows into Marathi, validate, and save to DB.
 */
function translateFlashcardsToMarathi(
    PDO $pdo,
    array $cardRows,
    string $subjectName = '',
    string $chapterName = '',
    string $boardName = 'Maharashtra State Board'
): array {
    if (empty($cardRows)) {
        return ['translated' => 0, 'skipped' => 0, 'errors' => [], 'items' => []];
    }
    if (!function_exists('callGeminiAPI')) {
        throw new Exception("Gemini helper 'callGeminiAPI' is not available.");
    }

    $byId = [];
    foreach ($cardRows as $r) {
        $byId[(int)$r['id']] = $r;
    }

    $prompt = buildFlashcardMarathiPrompt($cardRows, $subjectName, $chapterName, $boardName);
    $aiRaw = callGeminiAPI($prompt, [
        'temperature'      => 0.2,
        'maxOutputTokens'  => 8192,
        'responseMimeType' => 'application/json',
    ]);

    $parsed = decodeGeminiJsonResponse($aiRaw);
    $translations = $parsed['translations'] ?? [];
    if (!is_array($translations)) {
        throw new Exception('Invalid translations array returned by AI.');
    }

    $updateStmt = $pdo->prepare("
        UPDATE flashcards
        SET question_front_mr = ?, answer_back_mr = ?
        WHERE id = ?
    ");

    $translated = 0;
    $skipped = 0;
    $errors = [];
    $savedItems = [];

    foreach ($translations as $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0 || !isset($byId[$id])) {
            $skipped++;
            continue;
        }

        $qMr = trim((string)($item['question_front_mr'] ?? ''));
        $aMr = trim((string)($item['answer_back_mr'] ?? ''));

        if ($qMr === '' || $aMr === '') {
            $skipped++;
            $errors[] = "Flashcard #{$id}: empty front or back after translation.";
            continue;
        }

        $updateStmt->execute([$qMr, $aMr, $id]);
        $translated++;
        $savedItems[$id] = [
            'question_front_mr' => $qMr,
            'answer_back_mr'    => $aMr,
        ];
    }

    return [
        'translated' => $translated,
        'skipped'    => $skipped,
        'errors'     => $errors,
        'items'      => $savedItems,
    ];
}
