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

    // PDF Retrieval (DB or Disk)
    $pdfBase64 = $job['pdf_base64'];
    if (empty($pdfBase64)) {
        $filePath = $job['file_path'];
        if (file_exists($filePath)) {
            $pdfBase64 = base64_encode(file_get_contents($filePath));
        } else {
            throw new Exception("PDF data source missing.");
        }
    }

    sendProgress("Preparing Section Marker: Part $segment_index...", 25);

    // Optimized: Calculate approximate page range to give AI a focus area
    $pagesPerSegment = 5;
    $startPage = (($segment_index - 1) * $pagesPerSegment) + 1;
    $endPage = $startPage + $pagesPerSegment - 1;
    $rangeHint = "FOCUS RANGE: Approximately pages $startPage to $endPage.";

    // VEERU LENS CONTENT ENGINE PROMPT
    $systemPrompt = "You are the 'Veeru Lens Content Engine.' Your task is to perform an exhaustive, line-by-line extraction of educational content (MCQs, Flashcards, and Notes) from a PDF.

Operational Protocol:
1. Zero-Loss Extraction: Do not summarize. If a sentence contains a fact, it must be converted into a learning artifact.
2. Context Awareness: You are currently processing SECTION MARKER: Part $segment_index. $rangeHint Only process the text within this specific relative section to ensure maximum depth.
3. Avoid Duplication: Do not repeat information or questions from previous sections.

Output Format (Strict JSON):
{
  \"notes\": [\"Bulleted explanation 1\", \"Bulleted explanation 2\"],
  \"mcqs\": [
    {\"q\": \"Question?\", \"o\": [\"Opt A\", \"Opt B\", \"Opt C\", \"Opt D\"], \"a\": 0, \"e\": \"Explanation\"}
  ],
  \"flashcards\": [
    {\"q\": \"Question?\", \"a\": \"Answer\"}
  ]
}

Constraints:
- Maintain a 'Line-by-Line' reading logic.
- If content is technical/mathematical, show step-by-step logic in the notes.
- Answer in $language.";

    $userPrompt = "I have already generated content for the previous parts. Now, read the NEXT relative segment of the attached PDF (Section Marker: Part $segment_index).
Generate 15-20 NEW MCQs that were not in the previous batches.
Continue the Notes from where you left off.
Create Flashcards for any new definitions found in this specific segment.
DO NOT repeat information from previous segments.";

    sendProgress("Analyzing Section $segment_index with Gemini...", 50);

    // Call Gemini with the segmented prompt
    $aiResponse = callGeminiPDF($systemPrompt . "\n\n" . $userPrompt, $pdfBase64, [
        'temperature' => 0.3,
        'maxOutputTokens' => 8192
    ]);
    unset($pdfBase64); // Free memory immediately after use

    sendProgress("Polishing extracted artifacts...", 85);

    // Robust JSON parse with repair logic (same as pdf_worker_ai.php)
    $aiResponse = trim($aiResponse);
    $aiResponse = preg_replace('/^```json|```$/m', '', $aiResponse);
    $jsonStart = strpos($aiResponse, '{');
    $jsonEnd = strrpos($aiResponse, '}');

    if ($jsonStart === false) throw new Exception("AI failed to generate a structured response.");

    $cleanJson = ($jsonEnd !== false)
        ? substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1)
        : substr($aiResponse, $jsonStart);

    $data = json_decode($cleanJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Surgical repair for truncated JSON
        $repaired = rtrim($cleanJson, ", \n\r\t");
        $quotesCount = preg_match_all('/(?>\\"(?:[^"\\\\]|\\\\.)*\\"|"(?:[^"\\\\]|\\\\.)*")/', $repaired);
        if (substr_count($repaired, '"') % 2 !== 0) $repaired .= '"';
        $openBraces   = substr_count($repaired, '{') - substr_count($repaired, '}');
        $openBrackets = substr_count($repaired, '[') - substr_count($repaired, ']');
        for ($i = 0; $i < $openBrackets; $i++) $repaired .= ']';
        for ($i = 0; $i < $openBraces;   $i++) $repaired .= '}';
        $data = json_decode($repaired, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("AI response could not be parsed. Please try again.");
        }
    }

    if ($data) {
        echo "data: " . json_encode(['status' => 'success', 'data' => $data]) . "\n\n";
        ob_flush(); flush();
    } else {
        throw new Exception("AI returned an empty data structure.");
    }

    echo "data: [DONE]\n\n";
    ob_flush(); flush();

} catch (Exception $e) {
    echo "data: " . json_encode(['status' => 'error', 'message' => $e->getMessage()]) . "\n\n";
}
?>
