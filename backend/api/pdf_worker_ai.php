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

// --- Job Selection ---
$forceJobId = isset($_GET['force_job_id']) ? intval($_GET['force_job_id']) : 0;

if ($forceJobId > 0) {
    // Process a specific job (used for manual retries)
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$forceJobId]);
} else {
    // Pick next pending job (FIFO)
    $stmt = $pdo->query("SELECT * FROM pdf_study_jobs WHERE status = 'pending' ORDER BY job_id ASC LIMIT 1");
}
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

        // --- 1. PDF RETRIEVAL LOGIC (Railway-Proof) ---
        // If the file is missing from disk (ephemeral storage), we use the base64 from the DB.
        $pdfBase64 = '';
        
        if (!empty($job['pdf_base64'])) {
            // BEST CASE: We have the data in the DB
            $pdfBase64 = $job['pdf_base64'];
        } else {
            // FALLBACK: Try to read from disk
            $filePath = $job['file_path'];
            if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                $baseDir = dirname(__DIR__);
                $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $filePath;
            }

            if (file_exists($filePath)) {
                $pdfData = file_get_contents($filePath);
                $pdfBase64 = base64_encode($pdfData);
                unset($pdfData);
            } else {
                throw new Exception("PDF data missing: File not on disk and no base64 in DB.");
            }
        }
        
        // --- 1.5 PRE-FLIGHT DATA INTEGRITY CHECK ---
        if (empty($pdfBase64) || strlen($pdfBase64) < 100) {
            throw new Exception("PDF data is corrupted or missing (Length: " . strlen($pdfBase64) . ").");
        }

        $prompt = "Role: You are an Exhaustive Content Parser and Exam Developer. Your absolute priority is Total Information Coverage. Do not summarize; extract and transform.
        
        Objective: Analyze the provided PDF page text. Your goal is to convert every piece of factual, static, and conceptual data into either an MCQ or a Flashcard. If a page contains enough data for 50 questions, you must generate 50.
        
        SECTION 1: EXTRACTION PROTOCOLS
        - Zero-Skip Policy: Scan every line. If a fact exists (dates, names, definitions, processes, laws, formulas), it must become a question.
        - Granularity: Break complex paragraphs into multiple simple questions rather than one complex one.
        - Static Data focus: Ensure boring static data (tables, lists, year of establishment, etc.) is prioritized.
        
        SECTION 2: DIFFICULTY BALANCING
        For every 10 questions generated, maintain this ratio:
        - 3 Simple: Direct fact retrieval (e.g., 'When was X founded?').
        - 4 Moderate: Understanding and Comparison (e.g., 'Which of these is NOT a feature of X?').
        - 3 Hard: Application and Analysis (e.g., 'If X happens, what is the most likely result for Y?').
        
        SECTION 3: BATCHING & SET LOGIC
        Organize your generation process into 'Sets of 10' to maintain the difficulty ratio continuously throughout the document. However, you MUST output all generated questions into a single flat array within the JSON schema provided below. Do not create nested set objects.
        
        CRITICAL RULES:
        1. STRICT NATIVE LANGUAGE MATCH: If the PDF is written in Marathi, EVERY SINGLE output (questions, options, explanations, flashcards) MUST be in Marathi. If the PDF is English, output MUST be English. DO NOT translate the content.
        2. FORMAT: Return ONLY a valid JSON object. No markdown, no '```json' tags.
        
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
                $aiResponse = callGeminiPDF($prompt, $pdfBase64);
                if (!empty($aiResponse)) break;
            } catch (Exception $e) {
                if ($attempt == $maxRetries) throw $e;
                $wait = $attempt * 5;
                $pdo->prepare("UPDATE pdf_study_jobs SET error_message = ? WHERE job_id = ?")
                    ->execute(["AI Busy (Attempt $attempt). Retrying in {$wait}s...", $job['job_id']]);
                sleep($wait);
            }
        }

        unset($pdfBase64); // Free heavy base64 string

        // 3. Clean and Parse JSON
        $aiResponse = trim($aiResponse);
        // Remove markdown code blocks
        $aiResponse = preg_replace('/^```json|```$/m', '', $aiResponse);

        $jsonStart = strpos($aiResponse, '{');
        $jsonEnd = strrpos($aiResponse, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $cleanJson = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
        } else if ($jsonStart !== false) {
            // Truncated at end
            $cleanJson = substr($aiResponse, $jsonStart);
        } else {
            throw new Exception("AI response did not contain valid JSON structure.");
        }

        $data = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // SURGICAL REPAIR for truncated JSON
            $repaired = $cleanJson;
            
            // 1. Remove trailing incomplete property/value markers
            $repaired = rtrim($repaired, ", \n\r\t");
            
            // 2. If it ends inside a string, close the string
            // Check if there's an odd number of unescaped double quotes
            $quotesCount = preg_match_all('/(?<!\\\\)"/', $repaired);
            if ($quotesCount % 2 != 0) {
                $repaired .= '"';
            }

            // 3. Close open brackets and braces
            $openBraces   = substr_count($repaired, '{') - substr_count($repaired, '}');
            $openBrackets = substr_count($repaired, '[') - substr_count($repaired, ']');
            
            for ($i = 0; $i < $openBrackets; $i++) $repaired .= ']';
            for ($i = 0; $i < $openBraces;   $i++) $repaired .= '}';

            $data = json_decode($repaired, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON Repair Failed: " . json_last_error_msg());
            }
            $cleanJson = $repaired;
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