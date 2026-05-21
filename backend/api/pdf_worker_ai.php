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

// --- Job Selection with Atomic Claiming ---
$forceJobId = isset($_GET['force_job_id']) ? intval($_GET['force_job_id']) : 0;
$claimToken = bin2hex(random_bytes(8)); // Unique token for this worker instance

if ($forceJobId > 0) {
    // Force a specific job
    $stmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10, claim_token = ? WHERE job_id = ?");
    $stmt->execute([$claimToken, $forceJobId]);
    
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ? AND claim_token = ? LIMIT 1");
    $stmt->execute([$forceJobId, $claimToken]);
} else {
    // Atomically claim the NEXT pending job
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10, claim_token = ? 
                   WHERE status = 'pending' ORDER BY job_id ASC LIMIT 1")
        ->execute([$claimToken]);
    
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status = 'processing' AND claim_token = ? LIMIT 1");
    $stmt->execute([$claimToken]);
}
$jobs = $stmt->fetchAll();

if (empty($jobs)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'idle', 'message' => 'No pending jobs or claim failed.']);
    exit;
}

foreach ($jobs as $job) {
    try {
        // Status is already marked as 'processing' during claim

        // --- 1. PDF RETRIEVAL LOGIC (Railway-Proof) ---
        $pdfBase64 = '';
        $extractedText = $job['extracted_text'] ?? '';
        $dbData    = $job['pdf_base64'] ?? '';
        $isTruncated = (!empty($dbData) && strlen($dbData) < 10000); 

        if (!empty($dbData) && !$isTruncated) {
            $pdfBase64 = $dbData;
        } else {
            // FALLBACK 1: Disk
            $filePath = $job['file_path'];
            if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                $baseDir = dirname(__DIR__);
                $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $filePath;
            }

            if (file_exists($filePath)) {
                $pdfBase64 = base64_encode(file_get_contents($filePath));
            } elseif (!empty($dbData)) {
                $pdfBase64 = $dbData;
            }
        }

        // --- 1.5 DATA INTEGRITY & MASTER KNOWLEDGE FALLBACK ---
        if (empty($pdfBase64) && !empty($extractedText)) {
            // WE HAVE NO PDF BUT WE HAVE THE TEXT! 
            // We can still proceed by sending the text to Gemini instead of the file.
            error_log("[Veeru Worker] Proceeding with stored 'Master Knowledge' text (PDF wiped).");
        } elseif (empty($pdfBase64) || strlen($pdfBase64) < 100) {
            throw new Exception("PDF data is missing and no 'Master Knowledge' text found.");
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
        
        SECTION 1: THE \"DEEP-SCAN\" FLASHCARD PROTOCOL (QUESTION & ANSWER FORMAT)
        - Your ABSOLUTE priority is to create flashcards in a clear 'question' and 'answer' format.
        - NO SINGLE WORD QUESTIONS: Never use a single word as a question. Every flashcard question MUST be a complete, grammatically correct sentence.
        - STRICT DISTRIBUTION RATIO: You MUST maintain the following distribution in your output:
           1. 35% VERY SHORT ANSWER TYPE (Full questions requiring a precise 1-3 word answer).
           2. 35% SHORT ANSWER TYPE (Full questions needing a clear explanatory sentence).
           3. 30% FILL IN THE BLANKS (A complete sentence using ________ for the missing concept).
        - Coverage rules: 
           1. Extract all Definitions: Technical terms must have a definition card.
           2. Static Data: Capture dates, names, formulas, and specific figures.
           3. Basic Details: Cover foundational 'What', 'Why', and 'How'.
        - ATOMIC CLARITY: Each card MUST cover exactly ONE single concept. Format: {\"question\": \"Full Question Sentence?\", \"answer\": \"Full Answer Sentence or Phrase\"}.
        - RELEVANCE FILTER: Do NOT create questions from page numbers, footers, headers, or irrelevant decorative text. Focus exclusively on core educational content and high-value facts that a student actually needs to learn.
        - QUALITY AND EXHAUSTIVE EXTRACTION: Do not generate 'filler' questions, but do NOT miss a single important fact. Generate a flashcard for EVERY SINGLE piece of information, concept, definition, and fact present in the text to ensure 100% coverage.        
        - GOLD STANDARD EXAMPLES (FOLLOW THIS QUALITY LEVEL):
          1. [BAD]: \"Co-operation\" -> \"Working together.\"
          2. [GOOD]: \"What is the primary economic objective of a Co-operative society?\" -> \"To protect and promote the common economic interests of its members through mutual help.\"
          3. [BAD]: \"Farmers\" -> \"People who farm.\"
          4. [GOOD]: \"________ are the primary beneficiaries of the rural co-operative credit system in India.\" -> \"Small and marginal farmers\"
          5. [GOOD]: \"How does a co-operative society ensure democratic control among its members?\" -> \"By following the principle of 'one member, one vote' regardless of shareholding.\"
        SECTION 2: CONTENT LOAD BALANCING & DIFFICULTY
        - For every section of text you parse, aim for a balanced generation of MCQs and Flashcards. Do not stop generating Flashcards after just a few.
        - {$difficultyStr}
        
        SECTION 3: HIGH-VOLUME MCQ GENERATION & QUALITY STANDARDS
        - MAXIMIZE MCQ COUNT: You MUST generate as many relevant MCQs as possible from the provided text. Do not stop at just 5 or 10. Extract every single testable concept, fact, date, formula, and definition into a separate MCQ. Exhaustive coverage is your primary goal here.
        - Stem Length: Ensure question stems are meaningful and concise; avoid 'fluff' or irrelevant info.
        - Option Uniformity: All 4 options MUST be of roughly equal length. Never make the correct answer significantly longer than distractors.
        - Plausible Distractors: Distractors must be closely related to the topic and appear technically correct to non-experts. Avoid 'funny' or obviously wrong options.
        - Academic Language: Use plain, easy-to-understand language. Avoid unnecessarily complex jargon or 'tricky' phrasing.
        - Grammatical Matching: All options must match the stem's grammar perfectly to avoid giving away the answer via grammatical clues.
        - The explanation ('e') must concisely educate the student on WHY the correct answer is right and WHY distractors are incorrect.
        
        SECTION 4: SMART NOTES
        - Extract short, highly scannable bullet points across three explicit categories:
           1. definitions: Only core terminology and its meaning.
           2. key_facts: Essential dates, numbers, formulas, or unarguable static truths.
           3. core_concepts: Short explanations of 'how' or 'why' things work.
        - EXHAUSTIVE EXTRACTION: Do not limit to 3-5 points. Generate as many bullet points as needed to capture 100% of the vital information in the text. Do not miss a single concept or fact.
        
        CRITICAL RULES:
        1. STRICT NATIVE LANGUAGE MATCH: If the PDF is written in Marathi, EVERY SINGLE output (questions, options, explanations, flashcards) MUST be in Marathi. If the PDF is English, output MUST be English.
        2. FORMAT: Return ONLY a valid JSON object. No conversational text.
        3. 20% GENERATION RULE: You MUST extract the entire text of the document line-by-line into the 'full_text' field for our database. HOWEVER, to maintain quality, ONLY generate MCQs, Flashcards, and Notes based on the FIRST 20% (Part 1 of 5) of the document. Do not generate questions for the middle or end of the document yet.
        
        SCHEMA:
        {
          \"full_text\": \"Exhaustive, clean transcript of the entire document content\",
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
                if (!empty($pdfBase64)) {
                    $aiResponse = callGeminiPDF($prompt, $pdfBase64);
                } else {
                    // PDF is wiped, use the Master Knowledge text
                    $textPrompt = "Here is the Master Knowledge extracted from the document:\n\n" . $extractedText . "\n\n" . $prompt;
                    $aiResponse = callGeminiAPI($textPrompt);
                }
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
        // We use REPLACE to handle cases where a worker might retry or overlap, preventing duplicate key errors.
        $stmtContent = $pdo->prepare("REPLACE INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)");
        $stmtContent->execute([$job['job_id'], $job['user_id'], $cleanJson]);

        // 5. Update Job Status & Save Master Knowledge
        $fullText = $data['full_text'] ?? '';
        
        if (mb_strlen($fullText) > 100) {
            // Extraction was successful. Safe to wipe heavy PDF data to save space.
            $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100, error_message = NULL, extracted_text = ?, pdf_base64 = NULL WHERE job_id = ?");
            $updateStmt->execute([$fullText, $job['job_id']]);

            // 6. Cost Optimization: Delete the physical PDF file to save server space
            $fileToDelete = $job['file_path'];
            if (!empty($fileToDelete)) {
                if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $fileToDelete)) {
                    $baseDir = dirname(__DIR__);
                    $fileToDelete = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $fileToDelete;
                }
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                    error_log("[Veeru Worker] Deleted physical PDF file to save space: " . basename($fileToDelete));
                }
            }
        } else {
            // Extraction failed to grab full text. DO NOT wipe PDF data so we can try again or fallback.
            $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100, error_message = NULL, extracted_text = ? WHERE job_id = ?");
            $updateStmt->execute([$fullText, $job['job_id']]);
        }

    } catch (Exception $e) {
        error_log("Veeru Worker Error: " . $e->getMessage());
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
            ->execute([$e->getMessage(), $job['job_id']]);
    }
}
?>