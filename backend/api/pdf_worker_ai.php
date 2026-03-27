<?php
/**
 * Elite AI Worker: Gemini 2.0 Native PDF Processing
 * No manual parsing | Direct Vision | Recursive & Fast
 */
set_time_limit(300); // 5 minutes max
ignore_user_abort(true);

require_once '../config/db.php';
require_once '../config/ai_config.php';

// 1. Fetch pending jobs
$stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status = 'pending' LIMIT 3");
$stmt->execute();
$jobs = $stmt->fetchAll();

foreach ($jobs as $job) {
    try {
        // Mark as processing
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10 WHERE job_id = ?")->execute([$job['job_id']]);

        // Build absolute path - works on both Windows XAMPP and Railway Linux
        $uploadDir = dirname(__DIR__) . '/uploads/pdf_study/';
        $filePath = $uploadDir . $job['file_path'];
        
        // Also try the path stored if it already has the full path
        if (!file_exists($filePath)) {
            $filePath = '../' . $job['file_path'];
        }
        if (!file_exists($filePath)) throw new Exception("File not found: $filePath");

        // Convert PDF to Base64 for Native Vision API
        $pdfBase64 = base64_encode(file_get_contents($filePath));
        
        $prompt = "Act as a Senior Academic Evaluator.
Task: Convert this PDF into a structured JSON Study Pack.

Constraints:
1. Format: Output ONLY raw JSON. No markdown backticks.
2. Content: Analyze every page. Create 15-20 high-quality MCQs and 10 Flashcards.
3. Quality: Distractors must be plausible. Explanations must be helpful.

JSON Schema:
{
  \"mcqs\": [
    {\"q\": \"Question\", \"o\": [\"A\", \"B\", \"C\", \"D\"], \"a\": 0, \"e\": \"Explanation\"}
  ],
  \"flashcards\": [
    {\"f\": \"Front\", \"b\": \"Back\"}
  ]
}";

        // 2. Call Native Gemini PDF Vision (with 429 Retry)
        $aiResponse = "";
        $maxRetries = 3;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                $aiResponse = callGeminiPDF($prompt, $pdfBase64);
                break; // success
            } catch (Exception $e) {
                if (strpos($e->getMessage(), '429') !== false) {
                    $attempt++;
                    if ($attempt >= $maxRetries) throw $e;
                    $pdo->prepare("UPDATE pdf_study_jobs SET progress = 15, error_message = 'AI busy, retrying in 20s...' WHERE job_id = ?")->execute([$job['job_id']]);
                    sleep(20);
                } else {
                    throw $e;
                }
            }
        }
        
        // Clean JSON (remove potential md tags if AI ignored constraints)
        $json = trim($aiResponse);
        if (strpos($json, '```json') === 0) {
            $json = substr($json, 7, -3);
        }
        
        $data = json_decode($json, true);
        if (!$data || !isset($data['mcqs'])) {
             throw new Exception("AI returned invalid JSON: " . substr($json, 0, 100));
        }

        // 3. Save to database
        $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET study_content = ?, status = 'completed', progress = 100 WHERE job_id = ?");
        $updateStmt->execute([$json, $job['job_id']]);

        // Cleanup: Original PDF is no longer needed after sync (handled by app sync)
        
    } catch (Exception $e) {
        error_log("Worker Error (Job " . $job['job_id'] . "): " . $e->getMessage());
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")->execute([$e->getMessage(), $job['job_id']]);
    }
}
?>
