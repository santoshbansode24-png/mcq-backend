<?php
/**
 * Veeru Lens Content Engine: Segmented PDF Analysis
 * Implements the "Prompt Loop" for exhaustive extraction.
 */
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");
header("Access-Control-Allow-Origin: *");

require_once '../config/db.php';
require_once '../config/ai_config.php';

// Helper to send progress chunks
function sendProgress($msg, $progress = null) {
    echo "data: " . json_encode(['status' => 'progress', 'message' => $msg, 'progress' => $progress]) . "\n\n";
    ob_flush();
    flush();
}

try {
    $job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $segment_index = isset($_GET['segment_index']) ? intval($_GET['segment_index']) : 1;
    $language = in_array($_GET['language'] ?? '', ['English', 'Hindi', 'Marathi']) ? $_GET['language'] : 'English';

    if (!$job_id) throw new Exception("Job ID is required.");
    if (!$user_id) throw new Exception("User ID is required.");
    // Safety: Cap segment to prevent runaway generation
    if ($segment_index > 20) throw new Exception("Maximum scan depth (20 sections) reached.");

    sendProgress("Locating document in Vault...", 10);

    // Fetch Job Details — validates ownership in the same query
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $user_id]);
    $job = $stmt->fetch();

    if (!$job) throw new Exception("Job not found.");
    
    if ($job['status'] === 'pending' || $job['status'] === 'processing') {
        throw new Exception("PDF is still being analyzed. Please wait before using Veeru Lens.");
    }
    
    if ($job['status'] === 'failed') {
        throw new Exception("PDF analysis failed. Please re-upload the document.");
    }

    // PDF Retrieval (DB, Disk, or Master Knowledge Text)
    $pdfBase64 = $job['pdf_base64'];
    $extractedText = $job['extracted_text'] ?? '';

    if (empty($pdfBase64)) {
        $filePath = $job['file_path'];
        if (!empty($filePath) && !preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
            $baseDir = dirname(__DIR__);
            $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . $filePath;
        }

        if (!empty($filePath) && file_exists($filePath)) {
            $pdfBase64 = base64_encode(file_get_contents($filePath));
        } elseif (empty($extractedText)) {
            throw new Exception("This study pack was processed on an older version or text extraction failed. Please delete this study pack and re-upload the PDF to scan deeper sections!");
        }
    }

    sendProgress("Preparing Section Marker: Part $segment_index...", 25);

    // COST OPTIMIZATION: Divide text into 5 segments (20% each)
    $partStr = "Part $segment_index of 5";
    $rangeHint = "FOCUS RANGE: You are processing the $segment_index" . ($segment_index==2?"nd":($segment_index==3?"rd":"th")) . " 20% segment of the text.";

    // VEERU LENS CONTENT ENGINE PROMPT
    $systemPrompt = "You are the 'Veeru Lens Content Engine.' Your task is to perform an exhaustive, line-by-line extraction of educational content (MCQs, Flashcards, and Notes) from a specific portion of the provided text.

Operational Protocol:
1. Zero-Loss Extraction: Do not summarize. If a sentence contains a fact, it must be converted into a learning artifact.
2. Context Awareness: You are currently processing SECTION MARKER: $partStr. $rangeHint Only process the text within this specific 20% slice to ensure maximum depth.
3. Avoid Duplication: Do not repeat information or questions from previous sections. Focus ONLY on your assigned 20%.
4. HIGH-VOLUME GENERATION: You MUST generate as many relevant MCQs and Flashcards as possible from the provided text. Do not stop at just 1 or 2. Extract every single testable concept, fact, date, formula, and definition.

SECTION 1: FLASHCARDS (QUESTION & ANSWER FORMAT)
- Create flashcards in a clear 'question' and 'answer' format.
- Every flashcard question MUST be a complete, grammatically correct sentence.
- Format: {\"q\": \"Full Question Sentence?\", \"a\": \"Full Answer Sentence or Phrase\"}.
- Quality & Exhaustive Extraction: Generate a flashcard for every single piece of information, concept, definition, and fact present in the text to ensure 100% coverage.

SECTION 2: MULTIPLE CHOICE QUESTIONS
- Extract testable concepts into MCQs. Ensure distractors are plausible.
- Format: {\"q\": \"Question?\", \"o\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"], \"a\": 0, \"e\": \"Explanation why answer is correct\"}
- Maximize MCQ Count: Exhaustive coverage is your primary goal.

SECTION 3: SMART NOTES
- Extract short, highly scannable bullet points across three explicit categories:
   - definitions: Only core terminology and its meaning.
   - key_facts: Essential dates, numbers, formulas, or unarguable static truths.
   - core_concepts: Short explanations of 'how' or 'why' things work.

Output Format (Strict JSON):
{
  \"mcqs\": [
    {\"q\": \"Question?\", \"o\": [\"Opt A\", \"Opt B\", \"Opt C\", \"Opt D\"], \"a\": 0, \"e\": \"Explanation\"}
  ],
  \"flashcards\": [
    {\"q\": \"Question?\", \"a\": \"Answer\"}
  ],
  \"notes\": {
    \"definitions\": [\"Def 1\", \"Def 2\"],
    \"key_facts\": [\"Fact 1\", \"Fact 2\"],
    \"core_concepts\": [\"Concept 1\", \"Concept 2\"]
  }";

    if (!empty($pdfBase64)) {
        $systemPrompt .= ",\n  \"full_text\": \"Exhaustive transcript of the ENTIRE document (Required since master text is missing)\"";
    }

    $systemPrompt .= "\n}

Constraints:
- Maintain a 'Line-by-Line' reading logic.
- If content is technical/mathematical, show step-by-step logic in the notes.
- STRICT NATIVE LANGUAGE MATCH: If the source text is in Marathi, you MUST answer/generate in Marathi. If the source text is in Hindi, answer/generate in Hindi. Otherwise, answer in $language.";

    $userPrompt = "Now, read the specific segment: $partStr ($rangeHint).
    Generate as many NEW MCQs, Flashcards, and Notes as possible from THIS specific 20% segment.
    Maximize MCQ and flashcard count. Generate a card/question for EVERY SINGLE fact or concept. Do not stop after just one.
    DO NOT generate content from earlier or later parts of the text to avoid duplication.";

    sendProgress("Analyzing Section $segment_index with Gemini...", 50);

    $data = null;
    $maxRetries = 3;
    $lastError = "";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            // Call Gemini with the segmented prompt (File vs Text fallback)
            if (!empty($pdfBase64)) {
                $aiResponse = callGeminiPDF($systemPrompt . "\n\n" . $userPrompt, $pdfBase64, [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 8192
                ]);
            } else {
                // --- TOKEN OPTIMIZATION: SLICE THE MASTER KNOWLEDGE ---
                // Instead of sending 100,000 words, we only send the relevant chunk.
                $totalLen = mb_strlen($extractedText);
                $totalSegments = isset($job['total_chunks']) && intval($job['total_chunks']) > 0 ? intval($job['total_chunks']) : 5;
                $chunkSize = ceil($totalLen / $totalSegments);
                
                // Calculate start position with a 500-character overlap for context
                $start = ($segment_index - 1) * $chunkSize;
                if ($start > 500) $start -= 500; 
                
                // Extract only the relevant portion
                $slicedText = mb_substr($extractedText, $start, $chunkSize + 1000);

                $textPrompt = "### MASTER KNOWLEDGE SOURCE (SEGMENT $segment_index) ###\n" . $slicedText . "\n\n### TASK ###\n" . $systemPrompt . "\n\n" . $userPrompt;
                $aiResponse = callGeminiAPI($textPrompt, [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 8192
                ]);
            }

            if ($attempt === 1) {
                sendProgress("Polishing extracted artifacts...", 85);
            }

            // Robust JSON parse with repair logic (same as pdf_worker_ai.php)
            $aiResponse = trim($aiResponse);
            $aiResponse = preg_replace('/^```(?:json)?|```$/mi', '', $aiResponse);
            $jsonStart = strpos($aiResponse, '{');
            $jsonEnd = strrpos($aiResponse, '}');

            if ($jsonStart === false) throw new Exception("AI failed to generate a structured response.");

            $cleanJson = ($jsonEnd !== false)
                ? substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1)
                : substr($aiResponse, $jsonStart);

            // Fix Control Character Errors (Unescaped newlines/tabs inside strings)
            $cleanJson = str_replace(["\r", "\n", "\t"], " ", $cleanJson);

            $parsed = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Surgical repair for truncated JSON
                $repaired = rtrim($cleanJson, ", \n\r\t");
                if (substr_count($repaired, '"') % 2 !== 0) $repaired .= '"';
                $openBraces   = substr_count($repaired, '{') - substr_count($repaired, '}');
                $openBrackets = substr_count($repaired, '[') - substr_count($repaired, ']');
                for ($i = 0; $i < $openBrackets; $i++) $repaired .= ']';
                for ($i = 0; $i < $openBraces;   $i++) $repaired .= '}';
                $parsed = json_decode($repaired, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("AI response could not be parsed. Please try again.");
                }
            }

            if ($parsed) {
                $data = $parsed;
                break; // Success! Exit loop.
            } else {
                throw new Exception("AI returned an empty data structure.");
            }
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            if ($attempt < $maxRetries) {
                sendProgress("AI busy or failed, retrying (Attempt " . ($attempt + 1) . "/$maxRetries)...", 85);
                sleep(2);
            }
        }
    }

    unset($pdfBase64); // Free memory immediately after use

    if (!$data) {
        throw new Exception("Operation failed after $maxRetries attempts. Last error: $lastError");
    }

    // removed bad text overwrite logic

    echo "data: " . json_encode(['status' => 'success', 'data' => $data]) . "\n\n";
        ob_flush(); flush();

    echo "data: [DONE]\n\n";
    ob_flush(); flush();

} catch (Exception $e) {
    echo "data: " . json_encode(['status' => 'error', 'message' => $e->getMessage()]) . "\n\n";
}
?>
