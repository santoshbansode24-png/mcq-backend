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
    $stmt = $pdo->prepare("SELECT job_id, file_name, pdf_base64, study_content FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Study job not found");
    }

    $existingContent = json_decode($job['study_content'] ?? '{}', true) ?: [];
    if (!isset($existingContent['mcqs'])) $existingContent['mcqs'] = [];
    if (!isset($existingContent['flashcards'])) $existingContent['flashcards'] = [];
    if (!isset($existingContent['notes'])) $existingContent['notes'] = ['definitions' => [], 'key_facts' => [], 'core_concepts' => []];

    // 2. Prepare Gemini Prompt based on requested type
    $apiKey = getGeminiApiKey();
    if (empty($apiKey)) {
        throw new Exception("Gemini API key is not configured.");
    }

    $pdfBase64 = $job['pdf_base64'] ?? '';
    if (empty($pdfBase64)) {
        throw new Exception("Document source content missing for this job.");
    }

    if ($type === 'flashcards') {
        $prompt = "You are the Veeru Flashcard Generator.
STRICT PDF GROUND TRUTH DIRECTIVE: Every Flashcard MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided PDF document. Do NOT use outside knowledge.
Generate 10 high-quality, highly reliable Flashcards from the provided document.
Output strict JSON format ONLY:
{
  \"flashcards\": [
    {\"q\": \"Complete question sentence directly targeting a fact?\", \"a\": \"Exact accurate answer phrase or sentence\"}
  ]
}";
    } elseif ($type === 'mcqs') {
        $prompt = "You are the Veeru MCQ Quiz Generator.
STRICT PDF GROUND TRUTH DIRECTIVE: Every MCQ MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided PDF document. Do NOT use outside knowledge.
Generate 10 challenging Multiple Choice Questions from the provided document.
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
STRICT PDF GROUND TRUTH DIRECTIVE: Every Note point MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided PDF document. Do NOT use outside knowledge.
Generate comprehensive, highly scannable Revision Notes across definitions, key_facts, and core_concepts.
Output strict JSON format ONLY:
{
  \"notes\": {
    \"definitions\": [\"Term: Meaning\"],
    \"key_facts\": [\"Fact statement\"],
    \"core_concepts\": [\"Concept explanation\"]
  }
}";
    }

    // 3. Call Gemini 2.5 Flash API with PDF base64
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "inline_data" => [
                            "mime_type" => "application/pdf",
                            "data"      => $pdfBase64
                        ]
                    ],
                    [
                        "text" => $prompt
                    ]
                ]
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
        throw new Exception("Failed to parse AI response as JSON");
    }

    // 4. Merge new items cleanly into existing content
    if ($type === 'flashcards' && isset($newItems['flashcards']) && is_array($newItems['flashcards'])) {
        $existingContent['flashcards'] = array_merge($existingContent['flashcards'], $newItems['flashcards']);
        $addedCount = count($newItems['flashcards']);
    } elseif ($type === 'mcqs' && isset($newItems['mcqs']) && is_array($newItems['mcqs'])) {
        $existingContent['mcqs'] = array_merge($existingContent['mcqs'], $newItems['mcqs']);
        $addedCount = count($newItems['mcqs']);
    } elseif ($type === 'notes' && isset($newItems['notes']) && is_array($newItems['notes'])) {
        foreach (['definitions', 'key_facts', 'core_concepts'] as $cat) {
            if (isset($newItems['notes'][$cat]) && is_array($newItems['notes'][$cat])) {
                $existingContent['notes'][$cat] = array_values(array_unique(array_merge(
                    $existingContent['notes'][$cat] ?? [],
                    $newItems['notes'][$cat]
                )));
            }
        }
        $addedCount = count($newItems['notes']['definitions'] ?? []) + count($newItems['notes']['key_facts'] ?? []) + count($newItems['notes']['core_concepts'] ?? []);
    } else {
        throw new Exception("No new items generated by AI.");
    }

    // 5. Save updated content back to MySQL
    $updatedJson = json_encode($existingContent, JSON_UNESCAPED_UNICODE);
    $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET study_content = ? WHERE job_id = ?");
    $updateStmt->execute([$updatedJson, $jobId]);

    echo json_encode([
        'status'  => 'success',
        'message' => "Successfully generated $addedCount new $type!",
        'type'    => $type,
        'added'   => $addedCount,
        'data'    => $existingContent
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
