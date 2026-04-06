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
        // We prioritize the DB base64 for ephemeral environments, 
        // BUT we fallback to the disk file if the DB data is truncated or missing.
        $pdfBase64 = '';
        $dbData    = $job['pdf_base64'] ?? '';
        $isTruncated = (!empty($dbData) && strlen($dbData) < 10000); // Suspiciously small for a study PDF

        if (!empty($dbData) && !$isTruncated) {
            // BEST CASE: Full data is in the DB
            $pdfBase64 = $dbData;
        } else {
            // FALLBACK: Lead from disk if DB is empty or truncated
            $filePath = $job['file_path'];
            if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                $baseDir = dirname(__DIR__);
                $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $filePath;
            }

            if (file_exists($filePath)) {
                $pdfBase64 = base64_encode(file_get_contents($filePath));
                error_log("[Veeru Worker] Loaded PDF from disk fallback" . ($isTruncated ? " (DB was truncated)" : ""));
            } elseif (!empty($dbData)) {
                // No disk file, use whatever we have in DB (might still work if it's a tiny PDF)
                $pdfBase64 = $dbData;
            }
        }

        // --- 1.5 PRE-FLIGHT DATA INTEGRITY CHECK ---
        if (empty($pdfBase64) || strlen($pdfBase64) < 100) {
            throw new Exception("PDF data is corrupted or missing. Check MySQL max_allowed_packet.");
        }
        
        // --- 1.6 FINAL SIZE SANITY CHECK ---
        $base64Len = strlen($pdfBase64);
        error_log("[Veeru Worker] Job {$job['job_id']}: Final PDF base64 length = $base64Len bytes.");
        if ($base64Len < 10000 && $base64Len > 0) {
            // We allow it if it's truly a tiny PDF, but we log the warning
            error_log("[Veeru Worker] Warning: PDF data is very small ({$base64Len} bytes).");
        }

        $prompt = "Role: You are an Exhaustive Content Parser and Exam Developer. Your absolute priority is Total Information Coverage. Do not summarize; extract and transform.
        
        Objective: Analyze the provided PDF page text. Your goal is to convert every piece of factual, static, and conceptual data into BOTH an MCQ AND a Flashcard where appropriate. Do not spare any information. If a page has 50 facts, generate 50 MCQs AND 50 Flashcards.
        
        SECTION 1: EXTRACTION PROTOCOLS
        - Zero-Skip Policy: Scan every line. If a fact exists (dates, names, definitions, processes, laws, formulas), it must become a Flashcard or an MCQ or both.
        - Granularity: Break complex paragraphs into multiple simple data points.
        - Flashcard Priority: EVERY single keyword, date, event, person, or definition MUST be recorded as a Flashcard.
        
        SECTION 2: CONTENT LOAD BALANCING
        - For every section of text you parse, aim for a 1:1 ratio between MCQs and Flashcards.
        - Do not stop generating Flashcards after just a few. Create as many flashcards as you possibly can.
        
        SECTION 3: OUTPUT FORMATTING
        - All outputs must be merged into single flat arrays inside the JSON schema.
        - Generate the 'flashcards' array FIRST, before generating MCQs.
        
        CRITICAL RULES:
        1. STRICT NATIVE LANGUAGE MATCH: If the PDF is written in Marathi, EVERY SINGLE output (questions, options, explanations, flashcards) MUST be in Marathi. If the PDF is English, output MUST be English.
        2. FORMAT: Return ONLY a valid JSON object. No markdown, no '```json' tags.
        3. HIGH VOLUME DEMAND: I expect a massive amount of Flashcards. If you return 30 MCQs, you MUST return at least 30 Flashcards. DO NOT return fewer than 10 Flashcards unless the document is literally empty.
        
        SCHEMA:
        {
          \"flashcards\": [
            {\"front\": \"Front of card (Keyword/Concept)\", \"back\": \"Back of card (Definition/Explanation)\"}
          ],
          \"mcqs\": [
            {\"q\": \"Question\", \"o\": [\"A\", \"B\", \"C\", \"D\"], \"a\": 0, \"e\": \"Explanation\"}
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
                error_log("[Veeru Worker] Job {$job['job_id']} Attempt $attempt Error: " . $e->getMessage());
                if ($attempt == $maxRetries) throw $e;
                $wait = $attempt * 5;
                $pdo->prepare("UPDATE pdf_study_jobs SET error_message = ? WHERE job_id = ?")
                    ->execute(["AI Busy (Attempt $attempt/{$maxRetries}). Error: " . substr($e->getMessage(), 0, 200), $job['job_id']]);
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