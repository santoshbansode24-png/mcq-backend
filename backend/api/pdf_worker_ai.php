<?php
/**
 * Elite AI Worker: Gemini 2.0 Native PDF Processing
 * Optimized for Railway.app & Veeru App Production
 */
set_time_limit(600); // Increased to 10 mins for dense PDFs
ignore_user_abort(true);

require_once '../config/db.php';
require_once '../config/ai_config.php';

// Security Check: Only allow authorized triggers (App or Cron)
$workerKey = $_GET['key'] ?? ($_POST['key'] ?? '');
if ($workerKey !== WORKER_SECRET) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized worker trigger.']);
    exit;
}

// 1. Fetch pending jobs - processing 1 at a time prevents API Rate Limits (429)
$stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
$stmt->execute();
$jobs = $stmt->fetchAll();

if (empty($jobs)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'idle', 'message' => 'No pending jobs.']);
    exit;
}

foreach ($jobs as $job) {
    try {
        // Mark as processing immediately
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10 WHERE job_id = ?")
            ->execute([$job['job_id']]);

        // Absolute path logic
        $baseDir = dirname(__DIR__);
        $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $job['file_path'];

        if (!file_exists($filePath)) {
            throw new Exception("File not found at: " . $filePath);
        }

        // Convert PDF to Base64
        $pdfData = file_get_contents($filePath);
        $pdfBase64 = base64_encode($pdfData);
        unset($pdfData); // Free memory early

        $prompt = "You are an Educational Content Engine. Analyze this PDF and generate a comprehensive study pack.
        
        CRITICAL RULES:
        1. LANGUAGE: Match the PDF language (Marathi or English).
        2. COVERAGE: Extract 100% of factual data (Dates, Names, Laws, Scientific terms).
        3. MCQ QUALITY: 4 options, only 1 correct. Distractors must be plausible.
        4. EXPLANATION: Provide a 'why' for each answer, referencing the content.
        5. FORMAT: Return ONLY a valid JSON object. No markdown, no '```json' tags.
        
        SCHEMA:
        {
          \"mcqs\": [
            {\"q\": \"Question\", \"o\": [\"A\", \"B\", \"C\", \"D\"], \"a\": 0, \"e\": \"Explanation\"}
          ],
          \"flashcards\": [
            {\"f\": \"Front / Concept\", \"b\": \"Back / Definition\"}
          ]
        }";

        // 2. Call Gemini API with Retry Logic
        $aiResponse = "";
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Assuming callGeminiPDF handles the cURL to Google
                $aiResponse = callGeminiPDF($prompt, $pdfBase64);
                if (!empty($aiResponse))
                    break;
            } catch (Exception $e) {
                if ($attempt == $maxRetries)
                    throw $e;

                $wait = $attempt * 15; // Incremental wait: 15s, 30s
                $pdo->prepare("UPDATE pdf_study_jobs SET error_message = ? WHERE job_id = ?")
                    ->execute(["AI Busy (Attempt $attempt). Retrying in {$wait}s...", $job['job_id']]);
                sleep($wait);
            }
        }

        unset($pdfBase64); // Free heavy base64 string

        // 3. Clean and Parse JSON
        $aiResponse = trim($aiResponse);
        // Remove markdown code blocks if AI included them despite instructions
        $aiResponse = preg_replace('/^```json|```$/m', '', $aiResponse);

        $jsonStart = strpos($aiResponse, '{');
        $jsonEnd = strrpos($aiResponse, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $cleanJson = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
        } else {
            throw new Exception("AI response did not contain valid JSON structure.");
        }

        $data = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }

        // 4. Save to Content Table (Disposable Pattern)
        // We save the heavy JSON here, and the Sync script will wipe it later
        $stmtContent = $pdo->prepare("INSERT INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)");
        $stmtContent->execute([$job['job_id'], $job['user_id'], $cleanJson]);

        // 5. Update Job Status
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100, error_message = NULL WHERE job_id = ?")
            ->execute([$job['job_id']]);

    } catch (Exception $e) {
        error_log("Veeru Worker Error: " . $e->getMessage());
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
            ->execute([$e->getMessage(), $job['job_id']]);
    }
}
?>