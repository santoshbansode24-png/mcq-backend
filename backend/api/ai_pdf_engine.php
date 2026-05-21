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
            throw new Exception("PDF data source missing.");
        }
    }

    sendProgress("Preparing Section Marker: Part $segment_index...", 25);

    // COST OPTIMIZATION: Divide text into 10 segments (10% each)
    $partStr = "Part $segment_index of 10";
    $rangeHint = "FOCUS RANGE: You are processing the $segment_index" . ($segment_index==2?"nd":($segment_index==3?"rd":"th")) . " 10% segment of the text.";

    // VEERU LENS CONTENT ENGINE PROMPT
    $systemPrompt = "You are the 'Veeru Lens Content Engine.' Your task is to perform an exhaustive, line-by-line extraction of educational content (MCQs, Flashcards, and Notes) from a specific portion of the provided text.

Operational Protocol:
1. Zero-Loss Extraction: Do not summarize. If a sentence contains a fact, it must be converted into a learning artifact.
2. Context Awareness: You are currently processing SECTION MARKER: $partStr. $rangeHint Only process the text within this specific 10% slice to ensure maximum depth.
3. Avoid Duplication: Do not repeat information or questions from previous sections. Focus ONLY on your assigned 10%.

Output Format (Strict JSON):
{
  \"notes\": [\"Bulleted explanation 1\", \"Bulleted explanation 2\"],
  \"mcqs\": [
    {\"q\": \"Question?\", \"o\": [\"Opt A\", \"Opt B\", \"Opt C\", \"Opt D\"], \"a\": 0, \"e\": \"Explanation\"}
  ],
  \"flashcards\": [
    {\"q\": \"Question?\", \"a\": \"Answer\"}
  ]";

    if (!empty($pdfBase64)) {
        $systemPrompt .= ",\n  \"full_text\": \"Exhaustive transcript of the ENTIRE document (Required since master text is missing)\"";
    }

    $systemPrompt .= "\n}

Constraints:
- Maintain a 'Line-by-Line' reading logic.
- If content is technical/mathematical, show step-by-step logic in the notes.
- Answer in $language.";

    $userPrompt = "Now, read the specific segment: $partStr ($rangeHint).
Generate as many NEW MCQs, Flashcards, and Notes as possible from THIS specific 10% segment.
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
                $totalSegments = 10; // Let's support 10 deep scans by default
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
            $aiResponse = preg_replace('/^```json|```$/m', '', $aiResponse);
            $jsonStart = strpos($aiResponse, '{');
            $jsonEnd = strrpos($aiResponse, '}');

            if ($jsonStart === false) throw new Exception("AI failed to generate a structured response.");

            $cleanJson = ($jsonEnd !== false)
                ? substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1)
                : substr($aiResponse, $jsonStart);

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

    // COST & SPEED OPTIMIZATION: If we used the heavy PDF base64 and successfully retrieved full_text, 
    // save it permanently so the NEXT scan can use the fast/cheap text slicing method!
    if (isset($data['full_text']) && mb_strlen($data['full_text']) > 100) {
        $updateText = $pdo->prepare("UPDATE pdf_study_jobs SET extracted_text = ? WHERE job_id = ?");
        $updateText->execute([$data['full_text'], $job_id]);
    }

    echo "data: " . json_encode(['status' => 'success', 'data' => $data]) . "\n\n";
        ob_flush(); flush();

    echo "data: [DONE]\n\n";
    ob_flush(); flush();

} catch (Exception $e) {
    echo "data: " . json_encode(['status' => 'error', 'message' => $e->getMessage()]) . "\n\n";
}
?>
