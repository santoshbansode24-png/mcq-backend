<?php
/**
 * On-Demand Specific Item Generation Endpoint
 * Generates ONLY Flashcards, ONLY MCQs, or ONLY Notes for a specific PDF job.
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

$jobId = isset($_REQUEST['job_id']) ? intval($_REQUEST['job_id']) : 0;
$type  = isset($_REQUEST['type']) ? strtolower(trim($_REQUEST['type'])) : '';

if (!in_array($type, ['flashcard', 'flashcards', 'mcq', 'mcqs', 'notes'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type specified. Must be flashcards, mcqs, or notes.']);
    exit();
}

// Normalize type names
if ($type === 'flashcard') $type = 'flashcards';
if ($type === 'mcq') $type = 'mcqs';

if ($jobId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job_id']);
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

    // 2. Prepare Gemini Prompt based on requested type
    $apiKey = getGeminiApiKey();
    if (empty($apiKey)) {
        throw new Exception("Gemini API key is not configured.");
    }

    $pdfBase64 = $job['pdf_base64'] ?? '';
    $extractedText = $job['extracted_text'] ?? '';

    if ($type === 'flashcards') {
        $prompt = "You are the Veeru Flashcard Generator.
STRICT PDF GROUND TRUTH DIRECTIVE: Every Flashcard MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document. Do NOT use outside knowledge.
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
STRICT PDF GROUND TRUTH DIRECTIVE: Every MCQ MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document. Do NOT use outside knowledge.
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
STRICT PDF GROUND TRUTH DIRECTIVE: Every Note point MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document. Do NOT use outside knowledge.
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

    // 3. Build Gemini 2.5 Flash Request Parts
    if (!empty($pdfBase64)) {
        $parts = [
            [
                "inline_data" => [
                    "mime_type" => "application/pdf",
                    "data"      => $pdfBase64
                ]
            ],
            [
                "text" => $prompt
            ]
        ];
    } elseif (!empty($extractedText)) {
        $parts = [
            [
                "text" => "SOURCE TEXT DOCUMENT:\n" . substr($extractedText, 0, 30000) . "\n\nINSTRUCTION:\n" . $prompt
            ]
        ];
    } else {
        throw new Exception("Document source content is missing for this study pack.");
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    
    $payload = [
        "contents" => [
            [
                "parts" => $parts
            ]
        ],
        "generationConfig" => [
            "temperature"     => 0.2,
            "responseMimeType"=> "application/json"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception("Gemini connection error: " . $curlErr);
    }
    if ($httpCode !== 200) {
        throw new Exception("Gemini API error (HTTP $httpCode): " . substr($response, 0, 300));
    }

    $resData = json_decode($response, true);
    $aiText  = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';

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
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
