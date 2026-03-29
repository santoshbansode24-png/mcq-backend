<?php
/**
 * Upload PDF for AI Study Analysis
 * 1. Validates & saves the PDF file
 * 2. Creates a job record in DB
 * 3. Flushes "success" response to app immediately
 * 4. Runs AI processing inline (after response sent)
 *
 * WHY: Railway uses php -S (single-threaded). Firing a cURL to a
 * separate worker script blocks because the server can only handle
 * one request at a time. This inline approach solves that completely.
 */
set_time_limit(300); // 5 minutes for AI processing
ini_set('memory_limit', '512M');
ignore_user_abort(true); // Keep running even after response is sent

require_once '../config/db.php';
require_once '../config/ai_config.php';

file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "POST=" . json_encode($_POST) . " FILES=" . json_encode($_FILES) . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$job_id = null;
$targetPath = null;

try {
    // 1. Validate Input
    $user_id   = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
    if (!$user_id) throw new Exception("Unauthorized: user_id is required");

    // 2. Handle File Upload
    if (!isset($_FILES['pdf_file'])) throw new Exception("No file uploaded");
    $file      = $_FILES['pdf_file'];
    $fileName  = urldecode($file['name']);
    $tmpPath   = $file['tmp_name'];
    $fileError = $file['error'];
    if ($fileError !== 0) throw new Exception("Upload failed with error code: $fileError");
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new Exception("Only PDF files are allowed.");

    // 3. Save file to uploads directory
    $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeFileName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $uniqueFileName = time() . '_' . $user_id . '_' . $safeFileName;
    $targetPath     = $uploadDir . $uniqueFileName;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new Exception("Failed to save file. Upload dir: $uploadDir");
    }

    // 4. Create Job Record in DB
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, ?, 'pending', 10, 0)");
    $stmt->execute([$user_id, $folder_id, $fileName, $uniqueFileName]);
    $job_id = $pdo->lastInsertId();

    // 5. === FLUSH RESPONSE TO APP IMMEDIATELY ===
    // Send the success response now, before starting the slow AI work.
    // The app will see "success" and start polling for status.
    $responseBody = json_encode([
        'status'    => 'success',
        'message'   => 'PDF uploaded! AI is processing...',
        'job_id'    => $job_id,
        'file_name' => $fileName
    ]);
    header('Content-Type: application/json');
    header('Connection: close');
    header('Content-Length: ' . strlen($responseBody));
    echo $responseBody;
    
    // Flush all output buffers to send headers + body to the client now
    if (ob_get_level() > 0) ob_end_flush();
    flush();
    
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Response sent for job_id=$job_id. Starting inline AI...\n", FILE_APPEND);

} catch (Exception $e) {
    error_log("PDF Upload Error: " . $e->getMessage());
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "UPLOAD ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}

// ============================================================
// 6. INLINE AI PROCESSING (runs after response is already sent)
// ============================================================
if (!$job_id || !$targetPath || !file_exists($targetPath)) {
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Skipping AI: job_id or file missing.\n", FILE_APPEND);
    exit();
}

try {
    // Mark job as processing
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 20 WHERE job_id = ?")->execute([$job_id]);
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Job $job_id: marked processing, calling Gemini...\n", FILE_APPEND);

    // Convert PDF to Base64
    $pdfBase64 = base64_encode(file_get_contents($targetPath));

    $prompt = "Role: You are an expert Educational Content Creator and MCQ Generator.
Task: Analyze the uploaded PDF document page-by-page and generate Multiple Choice Questions (MCQs) and Flashcards for every single page without skipping any content.

1. Extraction Priority:
- Static Data: For each page, first identify all 'Static Data' (Dates, Names of People/Places, Specific Figures, Laws, Scientific Formulas, and Key Events). These must not be skipped.
- Conceptual Data: If a page lacks static data, focus on 'Conceptual Data.' Extract core theories, cause-and-effect relationships, definitions, and the primary logic.

2. Output Requirements:
- Question Volume: Produce as many questions/flashcards as needed to cover 100% of the content on dense pages, potentially up to 50 items. Do not summarize. Do not merge pages.
- MCQ Structure: 4 distinct, plausible, slightly similar options for distractors, ensuring the user must think. Only 1 correct answer.
- Flashcard Structure: Generate flashcards covering key definitions, concepts, and factual pairs from the content.
- Explanation: A brief explanation/rationale for the correct answer based on the text. Include context (e.g., 'From Page X').

3. Strict Constraints:
- Language: The generated MCQs and Flashcards MUST be in the exact same language as the uploaded PDF document (e.g. Marathi or English).
- Format: Output ONLY raw JSON matching the exact schema below! Do not include markdown backticks or prefixes outside the JSON. All data must fit inside the arrays.

JSON Schema:
{\"mcqs\": [{\"q\": \"Question...\", \"o\": [\"A\", \"B\", \"C\", \"D\"], \"a\": 0, \"e\": \"Explanation...\"}], \"flashcards\": [{\"f\": \"Front / Term\", \"b\": \"Back / Definition\"}]}";

    // Call Gemini with retry on 429 rate limit
    $aiResponse = "";
    $maxRetries = 3;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $aiResponse = callGeminiPDF($prompt, $pdfBase64);
            break; // success - exit loop
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '429') !== false && $attempt < $maxRetries - 1) {
                $pdo->prepare("UPDATE pdf_study_jobs SET progress = 30, error_message = 'AI busy, retrying...' WHERE job_id = ?")->execute([$job_id]);
                file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Job $job_id: 429 rate limit, sleeping 20s (attempt " . ($attempt+1) . ")\n", FILE_APPEND);
                sleep(20);
            } else {
                throw $e; // rethrow non-429 or final retry
            }
        }
    }

    // Clean JSON (strip markdown code fences if AI added them)
    $json = trim($aiResponse);
    if (strpos($json, '```json') !== false) {
        $json = preg_replace('/^```json\s*/i', '', $json);
        $json = preg_replace('/\s*```$/', '', $json);
        $json = trim($json);
    } elseif (strpos($json, '```') === 0) {
        $json = substr($json, 3);
        if (substr($json, -3) === '```') $json = substr($json, 0, -3);
        $json = trim($json);
    }

    $data = json_decode($json, true);
    if (!$data || !isset($data['mcqs'])) {
        throw new Exception("AI returned invalid JSON: " . substr($json, 0, 200));
    }

    // Save to database
    $pdo->prepare("UPDATE pdf_study_jobs SET study_content = ?, status = 'completed', progress = 100, error_message = NULL WHERE job_id = ?")->execute([$json, $job_id]);
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Job $job_id: COMPLETED. MCQs=" . count($data['mcqs']) . " Flashcards=" . count($data['flashcards'] ?? []) . "\n", FILE_APPEND);

} catch (Exception $e) {
    $errMsg = $e->getMessage();
    error_log("Inline AI Error (Job $job_id): $errMsg");
    file_put_contents('upload_debug.log', date('[Y-m-d H:i:s] ') . "Job $job_id: FAILED - $errMsg\n", FILE_APPEND);
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")->execute([$errMsg, $job_id]);
}
?>
