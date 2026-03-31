<?php
/**
 * Upload PDF for AI Study Analysis
 * 1. Validates & saves the PDF file
 * 2. Creates a job record in DB
 * 3. Runs AI processing synchronously
 * 4. Returns final response to app
 */
set_time_limit(300); // 5 minutes for AI processing
ini_set('memory_limit', '512M');

require_once '../config/db.php';
require_once '../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$job_id = null; // Track job_id so the catch block can update it

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
    if ($file['error'] !== 0) throw new Exception("Upload failed with error code: " . $file['error']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') throw new Exception("Only PDF files are allowed.");
    if ($file['size'] === 0) throw new Exception("File is empty (0 bytes). Your device may have denied storage read permissions.");

    // 3. Save file
    $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    
    $uniqueFileName = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $targetPath     = $uploadDir . $uniqueFileName;
    
    // On production/Railway, '/app/backend/uploads' might be read-only if not properly managed,
    // so we fallback to the OS temporary directory (which is safely writable).
    if (!@move_uploaded_file($tmpPath, $targetPath)) {
        $targetPath = sys_get_temp_dir() . '/' . $uniqueFileName;
        if (!@move_uploaded_file($tmpPath, $targetPath)) {
            // Absolute worst-case fallback: read directly from $tmpPath before it expires
            $targetPath = $tmpPath; 
        }
    }

    // 4. Create Job Record (status = processing so app knows it started)
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, folder_id, file_name, file_path, status, progress, total_pages) VALUES (?, ?, ?, ?, 'processing', 20, 0)");
    $stmt->execute([$user_id, $folder_id, $fileName, $uniqueFileName]);
    $job_id = $pdo->lastInsertId();

    // ================================================================
    // 5. SYNCHRONOUS AI PROCESSING
    // WHY SYNCHRONOUS: Railway uses php -S (single-threaded dev server,
    // NOT PHP-FPM). The "flush response then continue" trick only works
    // on Apache/Nginx + PHP-FPM. On php -S, the process gets killed
    // after the response is sent, so the AI code never completes and the
    // job stays stuck with a null error_message ("Unknown error" in app).
    // The fix: run AI here, THEN return the response. Client waits ~20-90s.
    // ================================================================
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

    // Call Gemini with retry on transient errors
    $aiResponse = "";
    $maxRetries = 3;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $pdo->prepare("UPDATE pdf_study_jobs SET progress = ? WHERE job_id = ?")->execute([30 + ($attempt * 15), $job_id]);
            $aiResponse = callGeminiPDF($prompt, $pdfBase64);
            break; // success
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
            $isTransient = (
                strpos($errMsg, '429') !== false ||
                strpos($errMsg, '500') !== false ||
                strpos($errMsg, '503') !== false ||
                stripos($errMsg, 'timeout') !== false ||
                stripos($errMsg, 'resolve') !== false
            );
            if ($isTransient && $attempt < $maxRetries - 1) {
                $pdo->prepare("UPDATE pdf_study_jobs SET progress = ?, error_message = ? WHERE job_id = ?")
                    ->execute([25 + ($attempt * 10), 'AI busy, retrying (' . ($attempt + 2) . '/3)...', $job_id]);
                sleep(15);
            } else {
                throw $e;
            }
        }
    }

    // Extract JSON from AI response
    $aiResponse = trim($aiResponse);
    $startIdx = strpos($aiResponse, '{');
    $endIdx   = strrpos($aiResponse, '}');
    $json = ($startIdx !== false && $endIdx !== false && $endIdx >= $startIdx)
        ? substr($aiResponse, $startIdx, $endIdx - $startIdx + 1)
        : $aiResponse;

    $data = json_decode($json, true);
    if (!$data || (!isset($data['mcqs']) && !isset($data['flashcards']))) {
        throw new Exception("AI returned an invalid format. Snippet: " . substr($json, 0, 200));
    }

    // Save completed result to DB
    $pdo->prepare("UPDATE pdf_study_jobs SET study_content = ?, status = 'completed', progress = 100, error_message = NULL WHERE job_id = ?")
        ->execute([$json, $job_id]);

    // Return success — app will reload data automatically
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'PDF analyzed! Your study materials are ready.',
        'job_id'    => $job_id,
        'file_name' => $fileName
    ]);

} catch (Exception $e) {
    $errMsg = $e->getMessage();
    error_log("PDF Upload/AI Error: " . $errMsg);

    // Always try to mark the job as failed with a real error message
    if (!empty($job_id)) {
        try {
            $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
                ->execute([$errMsg, $job_id]);
        } catch (Exception $dbEx) {
            error_log("Also failed to update job status: " . $dbEx->getMessage());
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $errMsg]);
    exit();
}
?>
