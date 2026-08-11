<?php
/**
 * Veeru Lens AI Worker: Gemini 2.0 Native Analysis
 * Optimized for Railway.app & Veeru App Production
 */
set_time_limit(600); // Increased to 10 mins for dense PDFs
ignore_user_abort(true);

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/../config/ai_config.php';

// Security Check: Only allow authorized triggers (App or Cron)
$workerKey = $_GET['key'] ?? ($_POST['key'] ?? '');
if ($workerKey !== WORKER_SECRET) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized worker trigger.']);
    return;
}

// --- Job Selection with Atomic Claiming ---
$forceJobId = isset($_GET['force_job_id']) ? intval($_GET['force_job_id']) : 0;

if ($forceJobId > 0) {
    $stmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10 WHERE job_id = ? AND status IN ('pending', 'processing', 'failed')");
    $stmt->execute([$forceJobId]);
    
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ? AND status = 'processing' LIMIT 1");
    $stmt->execute([$forceJobId]);
} else {
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 10 WHERE status = 'pending' ORDER BY job_id ASC LIMIT 1")
        ->execute();
    
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status = 'processing' ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
}
$jobs = $stmt->fetchAll();

if (empty($jobs)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'idle', 'message' => 'No pending jobs or claim failed.']);
    return;
}

foreach ($jobs as $job) {
    try {
        // Status is already marked as 'processing' during claim

        $pdfBase64 = '';
        $extractedText = $job['extracted_text'] ?? '';
        $dbData    = $job['pdf_base64'] ?? '';

        if (!empty($dbData)) {
            $pdfBase64 = $dbData;
        } else {
            // FALLBACK 1: Disk
            $filePath = $job['file_path'] ?? '';
            if (!empty($filePath)) {
                if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
                    $baseDir = dirname(__DIR__);
                    $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . basename($filePath);
                }

                if (file_exists($filePath)) {
                    $pdfBase64 = base64_encode(file_get_contents($filePath));
                }
            }
        }

        if (empty($extractedText) && !empty($pdfBase64)) {
            // STEP 1: Text Extraction Phase
            $textExtractionPrompt = "You are an OCR expert. Extract the ENTIRE text from this document exactly as it is written. Do not summarize or generate questions. Output ONLY the raw extracted text in plain text format. Do not use JSON or any formatting.";
            
            try {
                $textResponse = callGeminiPDF($textExtractionPrompt, $pdfBase64, [
                    'temperature' => 0.2,
                    'responseMimeType' => 'text/plain'
                ]);
                
                // Clean response string
                $textResponse = trim($textResponse);
                $textResponse = preg_replace('/^```(?:text|json)?\s*/i', '', $textResponse);
                $textResponse = preg_replace('/\s*```$/', '', $textResponse);
                $textResponse = trim($textResponse);

                // Fallback: If Gemini returned a JSON string object (e.g. {"text": "..."})
                if (strpos($textResponse, '{') === 0 && strpos($textResponse, '}') !== false) {
                    $parsed = @json_decode($textResponse, true);
                    if (is_array($parsed)) {
                        $parts = [];
                        array_walk_recursive($parsed, function($val) use (&$parts) {
                            if (is_string($val) && strlen(trim($val)) > 0) {
                                $parts[] = trim($val);
                            }
                        });
                        if (!empty($parts)) {
                            $textResponse = implode("\n", $parts);
                        }
                    }
                }
                
                if (!empty($textResponse) && strlen(trim($textResponse)) >= 10) {
                    $extractedText = trim($textResponse);
                }
            } catch (Exception $e) {
                error_log("Worker PDF Text Extraction Failed: " . $e->getMessage());
                throw new Exception("AI Extraction Error: " . $e->getMessage());
            }
        }

        // --- 1.5 DATA INTEGRITY & MASTER KNOWLEDGE FALLBACK ---
        if (empty($extractedText)) {
            throw new Exception("Failed to extract any text from the PDF. The file might be an empty image or corrupted.");
        }

        // STEP 2: Chunking Logic (Exactly 2 Chunks for 50% Segments)
        $words = explode(' ', $extractedText);
        $totalWords = count($words);
        $totalChunks = 2;
        
        $chunkSize = max(1, ceil($totalWords / $totalChunks));
        $chunks = array_chunk($words, $chunkSize);
        
        $currentChunkIndex = 0; // Processing Section 1
        $targetChunkText = implode(' ', $chunks[$currentChunkIndex] ?? []);

        // Update Job with Chunk Data before generation
        $pdo->prepare("UPDATE pdf_study_jobs SET extracted_text = ?, total_chunks = ?, last_processed_chunk = 0, pdf_base64 = NULL WHERE job_id = ?")
            ->execute([$extractedText, $totalChunks, $job['job_id']]);

        $difficultyStr = "Adapt difficulty based on the source text, ensuring a mix of foundational and advanced concepts.";

        // STEP 3: Generate Content for Chunk 0
        $prompt = "Role: You are Veeru Lens, an Expert Educational Content Creator specializing in Active Recall, Spaced Repetition, and rigorous assessment. Your absolute priority is high-quality, reliable, 100% FACTUAL information extraction.

        STRICT GROUND TRUTH DIRECTIVE: Every single MCQ, Flashcard, and Note MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided text below. Do NOT use outside knowledge, external facts, or hallucinate concepts not explicitly present in the source text.
        
        Objective: Analyze Section 1 of $totalChunks of this document text. Your goal is to convert factual, static, and conceptual data into BOTH MCQs AND 'Deep-Scan' Flashcards.
        
        SECTION 1: THE \"DEEP-SCAN\" FLASHCARD PROTOCOL (QUESTION & ANSWER FORMAT)
        - Your ABSOLUTE priority is to create flashcards in a clear, reliable 'question' and 'answer' format.
        - NO SINGLE WORD QUESTIONS: Never use a single word as a question. Every flashcard question MUST be a complete, grammatically correct sentence.
        - RELIABILITY & ACCURACY: Ensure the flashcard question directly targets a specific fact from the text, and the answer is 100% accurate and verifiable in the source text.
        - STRICT DISTRIBUTION RATIO: You MUST maintain the following distribution in your output:
           1. 35% VERY SHORT ANSWER TYPE (Full questions requiring a precise 1-3 word answer).
           2. 35% SHORT ANSWER TYPE (Full questions needing a clear explanatory sentence).
           3. 30% FILL IN THE BLANKS (A complete sentence using ________ for the missing concept).
        - Coverage rules: 
           1. Extract all Definitions: Technical terms must have a definition card.
           2. Static Data: Capture dates, names, formulas, and specific figures.
           3. Basic Details: Cover foundational 'What', 'Why', and 'How'.
        - ATOMIC CLARITY: Each card MUST cover exactly ONE single concept. Format: {\"question\": \"Full Question Sentence?\", \"answer\": \"Full Answer Sentence or Phrase\"}.
        - RELEVANCE FILTER: Do NOT create questions from page numbers, footers, headers, or irrelevant decorative text. Focus exclusively on core educational content and high-value facts.
        - QUALITY AND EXHAUSTIVE EXTRACTION: Do not generate 'filler' questions, but do NOT miss a single important fact. Generate a flashcard for EVERY SINGLE piece of information, concept, definition, and fact present in the text to ensure 100% coverage.        
        
        SECTION 2: CONTENT LOAD BALANCING & DIFFICULTY
        - 1:1 BALANCE RATIO: Maintain a strict 1:1 balance between MCQs and Flashcards. For every concept or fact you convert into a Flashcard, you must also generate a corresponding high-quality MCQ.
        - All three categories (mcqs, flashcards, and notes) are strictly mandatory and MUST be fully populated. Do not leave notes or any other section empty.
        - {$difficultyStr}
        
        SECTION 3: HIGH-VOLUME MCQ GENERATION & QUALITY STANDARDS
        - MAXIMIZE MCQ COUNT: You MUST generate as many relevant MCQs as possible from the provided text. Extract every single testable concept, fact, date, formula, and definition into a separate MCQ strictly grounded in the text.
        - Stem Length: Ensure question stems are meaningful and concise; avoid 'fluff' or irrelevant info.
        - Option Uniformity: All 4 options MUST be of roughly equal length. Never make the correct answer significantly longer than distractors.
        - Plausible Distractors: Distractors must be closely related to the topic and appear technically correct to non-experts. Avoid 'funny' or obviously wrong options.
        - Academic Language: Use plain, easy-to-understand language.
        - Grammatical Matching: All options must match the stem's grammar perfectly to avoid giving away the answer via grammatical clues.
        - The explanation ('e') must concisely educate the student on WHY the correct answer is right based strictly on the text.
        
        SECTION 4: SMART NOTES
        - Extract short, highly scannable bullet points across three explicit categories:
           1. definitions: Only core terminology and its meaning from the text.
           2. key_facts: Essential dates, numbers, formulas, or unarguable static truths.
           3. core_concepts: Short explanations of 'how' or 'why' things work.
        - EXHAUSTIVE EXTRACTION: Generate as many bullet points as needed to capture 100% of the vital information in the text.
        - LANGUAGE REQUIREMENT: Write definitions, key_facts, and core_concepts in the exact same language as the source text (e.g. Marathi if source text is in Marathi).
        
        CRITICAL RULES:
        1. STRICT PDF GROUND TRUTH: Zero external knowledge or hallucination. Everything must come directly from the source text.
        2. CRITICAL NATIVE LANGUAGE MANDATE: You MUST detect the language of the source text. If the source text is written in Marathi (मराठी), EVERY SINGLE generated MCQ (question, options, explanation), Flashcard (question, answer), and Note (definitions, key_facts, core_concepts) MUST BE 100% IN MARATHI (मराठी). Never translate Marathi into English. If source text is English, output MUST be English. If Hindi, output MUST be Hindi. Maintain 100% native language match.
        3. FORMAT: Return ONLY a valid JSON object. No markdown.
        4. CRITICAL MINIMUM QUOTA: You MUST generate a minimum of 3 MCQs, 3 Flashcards, and 3 bullet points for Notes, regardless of how short the text is. Rely strictly on the text to extract these. NEVER return an empty array for any category.
        
        SCHEMA:
        {
          \"mcqs\": [
            {\"q\": \"Question\", \"o\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"], \"a\": 0, \"e\": \"Explanation why answer is correct\"}
          ],
          \"flashcards\": [
            {\"question\": \"Question text or Fill-in-the-blank\", \"answer\": \"Answer text\"}
          ],
          \"notes\": {
            \"definitions\": [\"Def 1\", \"Def 2\"],
            \"key_facts\": [\"Fact 1\", \"Fact 2\"],
            \"core_concepts\": [\"Concept 1\", \"Concept 2\"]
          }
        }
        
        TEXT TO PROCESS (Section 1 of $totalChunks):
        " . $targetChunkText;

        // 2. Call Gemini API with Retry Logic (Text only, since PDF was already parsed)
        $aiResponse = "";
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $aiResponse = callGeminiAPI($prompt, [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 8192,
                    'responseMimeType' => 'application/json'
                ]);
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

        // Fix Control Character Errors (Unescaped newlines/tabs inside strings)
        $cleanJson = str_replace(["\r", "\n", "\t"], " ", $cleanJson);

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

        // 5. Update Job Status
        // Since we already saved extracted_text in Step 2, we just mark as completed.
        $updateStmt = $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100, error_message = NULL, last_processed_chunk = 1 WHERE job_id = ?");
        $updateStmt->execute([$job['job_id']]);

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

    } catch (Throwable $e) {
        error_log("Veeru Worker Error: " . $e->getMessage());
        $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
            ->execute([$e->getMessage(), $job['job_id']]);
    }
}
?>