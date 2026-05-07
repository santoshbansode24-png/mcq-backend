<?php
/**
 * Veeru Lens Content Engine: Segmented PDF Analysis
 * Implements the "Prompt Loop" for exhaustive extraction.
 */
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");

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
    $segment_index = isset($_GET['segment_index']) ? intval($_GET['segment_index']) : 1;
    $language = $_GET['language'] ?? 'English';

    if (!$job_id) throw new Exception("Job ID is required.");

    sendProgress("Locating document in Vault...", 10);

    // Fetch Job Details
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ?");
    $stmt->execute([$job_id]);
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

    // VEERU LENS CONTENT ENGINE PROMPT
    $systemPrompt = "You are the 'Veeru Lens Content Engine.' Your task is to perform an exhaustive, line-by-line extraction of educational content (MCQs, Flashcards, and Notes) from a PDF.

Operational Protocol:
1. Zero-Loss Extraction: Do not summarize. If a sentence contains a fact, it must be converted into a learning artifact.
2. Context Awareness: You are currently processing SECTION MARKER: Part $segment_index. Only process the text within this specific relative section to ensure maximum depth.
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

    // Call Gemini
    $aiResponse = callGeminiPDF($systemPrompt . "\n\n" . $userPrompt, $pdfBase64, [
        'temperature' => 0.3,
        'maxOutputTokens' => 8192
    ]);

    sendProgress("Polishing extracted artifacts...", 80);

    // Parse JSON
    $jsonStart = strpos($aiResponse, '{');
    $jsonEnd = strrpos($aiResponse, '}');
    if ($jsonStart !== false && $jsonEnd !== false) {
        $cleanJson = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($cleanJson, true);

        if ($data) {
            // Success! Return the content
            echo "data: " . json_encode(['status' => 'success', 'data' => $data]) . "\n\n";
        } else {
            throw new Exception("AI returned invalid data format.");
        }
    } else {
        throw new Exception("AI failed to generate a structured response.");
    }

    echo "data: [DONE]\n\n";

} catch (Exception $e) {
    echo "data: " . json_encode(['status' => 'error', 'message' => $e->getMessage()]) . "\n\n";
}
?>
