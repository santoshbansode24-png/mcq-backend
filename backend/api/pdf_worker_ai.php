<?php
/**
 * Veeru Lens AI Worker: Gemini 2.0 Native Analysis
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

        $difficulty = $job['difficulty'] ?? 'mix';
        $difficultyStr = "";
        if ($difficulty === 'easy') {
            $difficultyStr = "DIFFICULTY LEVEL: EASY\n- Use very simple, easy-to-understand language.\n- Focus on direct definitions, basic concepts, and surface-level facts.\n- For MCQs, create slightly easier distractors (though still not completely obvious throwaways).";
        } elseif ($difficulty === 'moderate') {
            $difficultyStr = "DIFFICULTY LEVEL: MODERATE\n- Use standard high-school or introductory-college academic language.\n- Test mid-level understanding and standard curriculum facts.";
        } elseif ($difficulty === 'hard') {
            $difficultyStr = "DIFFICULTY LEVEL: HARD\n- Use advanced framing, deep conceptual analysis, and test intricate details.\n- Focus on complex mechanisms, abstract concepts, and 'Why' frameworks.\n- For MCQs, use extremely challenging and highly plausible distractors that require deep analytical thought.";
        } else {
            $difficultyStr = "DIFFICULTY LEVEL: MIX (Default)\n- Balance the generated content difficulty. Aim for 30% easy foundational questions, 40% moderate standard questions, and 30% hard analytical questions across both Flashcards and MCQs.";
        }

        $prompt = "Role: You are Veeru Lens, an Expert Educational Content Creator specializing in Active Recall, Spaced Repetition, and rigorous assessment. Your absolute priority is high-quality information extraction. Do not summarize; extract and transform.
        
        Objective: Analyze the provided PDF page text. Your goal is to convert factual, static, and conceptual data into BOTH MCQs AND 'Deep-Scan' Flashcards.
        
        SECTION 1: THE \"DEEP-SCAN\" FLASHCARD PROTOCOL
        - Coverage rules: 
           1. Extract all Definitions: Every technical term or concept must have a definition card.
           2. Static Data: Capture all dates, names, formulas, and specific figures.
           3. Basic Details: Ensure foundational 'What', 'Why', and 'How' questions are covered.
        - STRICT FORMAT AND DISTRIBUTION: Create flashcards STRICTLY in a `question` and `answer` format. Your flashcard distribution MUST closely adhere to the following ratios:
           1. 35% VERY SHORT ANSWER TYPE (Direct, concise questions with 1-2 word answers).
           2. 35% SHORT ANSWER TYPE (Questions requiring a short phrase or single sentence answer).
           3. 30% FILL IN THE BLANKS (Statements with a ________ for the missing key concept).
        - ATOMIC CLARITY: Each card MUST cover exactly ONE single concept or fact. If a card is too complex, break it down.
        SECTION 2: CONTENT LOAD BALANCING & DIFFICULTY
        - For every section of text you parse, aim for a balanced generation of MCQs and Flashcards. Do not stop generating Flashcards after just a few.
        - {$difficultyStr}
        
        SECTION 3: STRICT QUALITY STANDARDS FOR MCQs
        - Stem Length: Ensure question stems are meaningful and concise; avoid "fluff" or irrelevant info.
        - Option Uniformity: All 4 options MUST be of roughly equal length. Never make the correct answer significantly longer than distractors.
        - Plausible Distractors: Distractors must be closely related to the topic and appear technically correct to non-experts. Avoid "funny" or obviously wrong options.
        - Academic Language: Use plain, easy-to-understand language. Avoid unnecessarily complex jargon or "tricky" phrasing.
        - Grammatical Matching: All options must match the stem's grammar perfectly to avoid giving away the answer via grammatical clues.
        - The explanation ('e') must concisely educate the student on WHY the correct answer is right and WHY distractors are incorrect.
        
        SECTION 4: SMART NOTES
        - Extract ultra-short, highly scannable bullet points across three explicit categories:
           1. definitions: Only core terminology and its meaning.
           2. key_facts: Essential dates, numbers, formulas, or unarguable static truths.
           3. core_concepts: Short explanations of 'how' or 'why' things work.
        - Generate exactly 3-5 high-value bullet points per category.
        
        CRITICAL RULES:
        1. STRICT NATIVE LANGUAGE MATCH: If the PDF is written in Marathi, EVERY SINGLE output (questions, options, explanations, flashcards) MUST be in Marathi. If the PDF is English, output MUST be English.
        2. FORMAT: Return ONLY a valid JSON object. No conversational text.
        3. VOLUME DEMAND: I expect a massive amount of output. Generate up to the maximum constraints possible for the extracted text. DO NOT return fewer than 10 Flashcards unless the document is literally empty.
        
        SCHEMA:
        {
          \"notes\": {
            \"definitions\": [\"Def 1\", \"Def 2\"],
            \"key_facts\": [\"Fact 1\", \"Fact 2\"],
            \"core_concepts\": [\"Concept 1\", \"Concept 2\"]
          },
          \"flashcards\": [
            {\"question\": \"Question text or Fill-in-the-blank\", \"answer\": \"Answer text\"}
          ],
          \"mcqs\": [
            {\"q\": \"Question\", \"o\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"], \"a\": 0, \"e\": \"Explanation why answer is correct\"}
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