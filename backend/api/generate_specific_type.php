<?php
/**
 * On-Demand Specific Item Generation Endpoint
 * Generates ONLY Flashcards, ONLY MCQs, or ONLY Notes for a specific PDF job.
 * Optimized for low-cost token consumption & fast responses.
 */
if (file_exists(__DIR__ . '/cors_middleware.php')) {
    require_once __DIR__ . '/cors_middleware.php';
} elseif (file_exists(__DIR__ . '/backend/api/cors_middleware.php')) {
    require_once __DIR__ . '/backend/api/cors_middleware.php';
}
set_time_limit(300);
ini_set('memory_limit', '512M');

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$inputRaw = file_get_contents('php://input');
$jsonInput = json_decode($inputRaw, true) ?: [];

$jobId = 0;
if (isset($_REQUEST['job_id']) && intval($_REQUEST['job_id']) > 0) {
    $jobId = intval($_REQUEST['job_id']);
} elseif (isset($jsonInput['job_id']) && intval($jsonInput['job_id']) > 0) {
    $jobId = intval($jsonInput['job_id']);
} elseif (preg_match('/name=["\']job_id["\']\s+([0-9]+)/i', $inputRaw, $m)) {
    $jobId = intval($m[1]);
}

$type = '';
if (isset($_REQUEST['type']) && !empty($_REQUEST['type'])) {
    $type = strtolower(trim($_REQUEST['type']));
} elseif (isset($jsonInput['type']) && !empty($jsonInput['type'])) {
    $type = strtolower(trim($jsonInput['type']));
} elseif (preg_match('/name=["\']type["\']\s+([a-zA-Z]+)/i', $inputRaw, $m)) {
    $type = strtolower(trim($m[1]));
}

// Normalize type names
if ($type === 'flashcard') $type = 'flashcards';
if ($type === 'mcq') $type = 'mcqs';

if (!in_array($type, ['flashcard', 'flashcards', 'mcq', 'mcqs', 'notes'])) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Invalid type specified. Must be flashcards, mcqs, or notes.']);
    exit();
}

if ($jobId <= 0) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'Invalid job_id provided.']);
    exit();
}

try {
    // 1. Fetch job from DB
    $stmt = $pdo->prepare("SELECT job_id, user_id, file_name, pdf_base64, extracted_text, study_content FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Study session not found for ID: $jobId");
    }

    // Read existing content from pdf_study_content (primary) or pdf_study_jobs (fallback)
    $existingContent = [];
    $stmtContent = $pdo->prepare("SELECT study_pack_json FROM pdf_study_content WHERE job_id = ? LIMIT 1");
    $stmtContent->execute([$jobId]);
    $contentRow = $stmtContent->fetch(PDO::FETCH_ASSOC);

    if ($contentRow && !empty($contentRow['study_pack_json'])) {
        $existingContent = json_decode($contentRow['study_pack_json'], true) ?: [];
    } else if (!empty($job['study_content'])) {
        $existingContent = json_decode($job['study_content'], true) ?: [];
    }

    if (!isset($existingContent['mcqs']) || !is_array($existingContent['mcqs'])) $existingContent['mcqs'] = [];
    if (!isset($existingContent['flashcards']) || !is_array($existingContent['flashcards'])) $existingContent['flashcards'] = [];
    if (!isset($existingContent['notes']) || !is_array($existingContent['notes'])) {
        $existingContent['notes'] = ['definitions' => [], 'key_facts' => [], 'core_concepts' => []];
    }

    $count = isset($_REQUEST['count']) ? intval($_REQUEST['count']) : 10;
    if ($count < 5) $count = 10;
    if ($count > 30) $count = 20;

    // Build exclusion list of existing items to guarantee zero duplicates
    $existingQuestions = [];
    if ($type === 'flashcards' && !empty($existingContent['flashcards'])) {
        foreach ($existingContent['flashcards'] as $card) {
            $q = trim($card['q'] ?? $card['question'] ?? '');
            if ($q) $existingQuestions[] = $q;
        }
    } elseif ($type === 'mcqs' && !empty($existingContent['mcqs'])) {
        foreach ($existingContent['mcqs'] as $mcq) {
            $q = trim($mcq['q'] ?? $mcq['question'] ?? '');
            if ($q) $existingQuestions[] = $q;
        }
    }

    $exclusionClause = "";
    if (!empty($existingQuestions)) {
        $recent = array_slice($existingQuestions, -30);
        $exclusionClause = "\nDO NOT GENERATE ANY QUESTIONS DUPLICATING THESE EXISTING ITEMS:\n- " . implode("\n- ", $recent) . "\n";
    }

    $pdfBase64 = $job['pdf_base64'] ?? '';
    $extractedText = $job['extracted_text'] ?? '';

    // If pdfBase64 is empty, check if physical disk file exists
    if (empty($pdfBase64)) {
        $filePath = $job['file_path'] ?? '';
        if (!empty($filePath)) {
            if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                $baseDir = dirname(__DIR__);
                $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . basename($filePath);
            }
            if (file_exists($filePath)) {
                $pdfBase64 = base64_encode(file_get_contents($filePath));
            }
        }
    }

    if ($type === 'flashcards') {
        $prompt = "You are the Veeru Flashcard Generator.
STRICT PDF GROUND TRUTH DIRECTIVE: Every Flashcard MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document text. Do NOT use outside knowledge.
CRITICAL NATIVE LANGUAGE MANDATE: You MUST detect the language of the provided document text. If the text is written in Marathi (मराठी), EVERY SINGLE question and answer MUST BE WRITTEN IN MARATHI (मराठी). Never translate Marathi content into English. If English, output in English. If Hindi, output in Hindi.
Generate $count high-quality, highly reliable NEW Flashcards from the provided document.
$exclusionClause
Output strict JSON format ONLY:
{
  \"flashcards\": [
    {\"q\": \"Complete question sentence directly targeting a fact?\", \"a\": \"Exact accurate answer phrase or sentence\"}
  ]
}";
    } elseif ($type === 'mcqs') {
        $prompt = "You are the Veeru MCQ Quiz Generator.
STRICT PDF GROUND TRUTH DIRECTIVE: Every MCQ MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document text. Do NOT use outside knowledge.
CRITICAL NATIVE LANGUAGE MANDATE: You MUST detect the language of the provided document text. If the text is written in Marathi (मराठी), EVERY SINGLE question, option, and explanation MUST BE WRITTEN IN MARATHI (मराठी). Never translate Marathi content into English. If English, output in English. If Hindi, output in Hindi.
Generate $count challenging Multiple Choice Questions from the provided document.
$exclusionClause
Output strict JSON format ONLY:
{
  \"mcqs\": [
    {
      \"q\": \"Question?\",
      \"o\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
      \"a\": 0,
      \"e\": \"Explanation why answer is correct\"
    }
  ]
}";
    } else { // notes
        $prompt = "You are the Veeru Smart Revision Notes Engine.
STRICT PDF GROUND TRUTH DIRECTIVE: Every Note point MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document text. Do NOT use outside knowledge.
CRITICAL NATIVE LANGUAGE MANDATE: You MUST detect the language of the provided document text. If the document text is written in Marathi (मराठी), EVERY SINGLE note entry (definitions, key_facts, core_concepts) MUST BE WRITTEN ENTIRELY IN MARATHI (मराठी). Never translate Marathi source text into English. If English, generate in English. If Hindi, generate in Hindi. Match the native language of the source text 100%.
Generate comprehensive, highly scannable, expanded Revision Notes across definitions, key_facts, and core_concepts (extract at least 15 new key points).
Output strict JSON format ONLY:
{
  \"notes\": {
    \"definitions\": [\"Term: Meaning\"],
    \"key_facts\": [\"Fact statement\"],
    \"core_concepts\": [\"Concept explanation\"]
  }
}";
    }

    // 3. Call Gemini Helper Functions
    $aiText = "";
    if (!empty($extractedText)) {
        $fullPrompt = "SOURCE TEXT DOCUMENT:\n" . substr($extractedText, 0, 35000) . "\n\nINSTRUCTION:\n" . $prompt;
        $aiText = callGeminiAPI($fullPrompt, [
            'temperature' => 0.2,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json'
        ]);
    } elseif (!empty($pdfBase64)) {
        $aiText = callGeminiPDF($prompt, $pdfBase64, [
            'temperature' => 0.2,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json'
        ]);
    } else {
        throw new Exception("Document source content is missing for this study pack.");
    }

    // Strip markdown code fences if present
    $aiText = preg_replace('/^```json\s*/i', '', trim($aiText));
    $aiText = preg_replace('/^```\s*/i', '', $aiText);
    $aiText = preg_replace('/\s*```$/i', '', $aiText);

    $newItems = json_decode($aiText, true);

    if (!$newItems) {
        throw new Exception("Failed to parse AI response as JSON.");
    }

    // 4. Ultra-Resilient JSON Response Normalization & Merging
    $addedCount = 0;

    if ($type === 'flashcards') {
        $cards = $newItems['flashcards'] ?? $newItems['cards'] ?? (is_array($newItems) && isset($newItems[0]) ? $newItems : []);
        if (!empty($cards) && is_array($cards)) {
            $existingContent['flashcards'] = array_merge($existingContent['flashcards'], $cards);
            $addedCount = count($cards);
        } else {
            throw new Exception("No new flashcards generated by AI.");
        }
    } elseif ($type === 'mcqs') {
        $mcqs = $newItems['mcqs'] ?? $newItems['questions'] ?? (is_array($newItems) && isset($newItems[0]) ? $newItems : []);
        if (!empty($mcqs) && is_array($mcqs)) {
            $existingContent['mcqs'] = array_merge($existingContent['mcqs'], $mcqs);
            $addedCount = count($mcqs);
        } else {
            throw new Exception("No new MCQs generated by AI.");
        }
    } elseif ($type === 'notes') {
        $notesObj = isset($newItems['notes']) && is_array($newItems['notes']) ? $newItems['notes'] : $newItems;
        foreach (['definitions', 'key_facts', 'core_concepts'] as $cat) {
            $catItems = $notesObj[$cat] ?? $notesObj[ucfirst($cat)] ?? [];
            if (!empty($catItems) && is_array($catItems)) {
                $existingContent['notes'][$cat] = array_values(array_unique(array_merge(
                    $existingContent['notes'][$cat] ?? [],
                    $catItems
                )));
                $addedCount += count($catItems);
            }
        }
        if ($addedCount === 0) {
            throw new Exception("No new revision notes extracted by AI.");
        }
    }

    // 5. Save updated content back to MySQL DB
    $updatedJson = json_encode($existingContent, JSON_UNESCAPED_UNICODE);

    // Save into pdf_study_content (Primary)
    $stmtSave = $pdo->prepare("REPLACE INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)");
    $stmtSave->execute([$jobId, $job['user_id'] ?? 0, $updatedJson]);

    // Save into pdf_study_jobs (Fallback)
    try {
        $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET study_content = ? WHERE job_id = ?");
        $updateStmt->execute([$updatedJson, $jobId]);
    } catch (Throwable $t) {}

    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully generated expanded $type!",
        'type'    => $type,
        'added'   => $addedCount,
        'data'    => $existingContent
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
