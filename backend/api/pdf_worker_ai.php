<?php
/**
 * Hardened PDF-to-Exam Background AI Worker
 * Optimized for Railway/Production timeouts.
 */

// 🏎️ 1. Resource and Connection Management
set_time_limit(300); // 5 minutes max per run
ini_set('memory_limit', '512M'); // Increase for PDF parsing
ignore_user_abort(true);
header('Content-Type: text/plain'); // Direct text response for live monitoring
header('X-Accel-Buffering: no'); // Disable Nginx buffering to allow live flush

require_once '../config/db.php';
require_once '../config/ai_config.php';
require_once '../../vendor/autoload.php';

use Smalot\PdfParser\Parser;

function echoHeartbeat($msg) {
    echo "[" . date('H:i:s') . "] " . $msg . "\n";
    ob_flush();
    flush();
}

echoHeartbeat("Starting Worker...");

// 🧠 2. Find a pending job
$stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status IN ('pending', 'processing') ORDER BY created_at ASC LIMIT 1");
$stmt->execute();
$job = $stmt->fetch();

if (!$job) {
    echoHeartbeat("No pending jobs found. Standing by.");
    exit();
}

$jobId = $job['job_id'];
$userId = $job['user_id'];
$filePath = '../../uploads/pdf_study/' . $job['file_path'];

try {
    echoHeartbeat("Processing Job $jobId: " . $job['file_name']);

    // 🚦 3. Signal that we are starting (5% progress)
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 5 WHERE job_id = ?")->execute([$jobId]);

    if (!file_exists($filePath)) {
        throw new Exception("PDF file not found at " . realpath($filePath));
    }

    echoHeartbeat("Parsing PDF Text...");
    // 📖 4. Parse PDF Text with Error Catching
    $parser = new Parser();
    try {
        $pdf = $parser->parseFile($filePath);
    } catch (Exception $pe) {
        throw new Exception("PDF Parsing Error: " . $pe->getMessage());
    }
    
    $pages = $pdf->getPages();
    $totalPages = count($pages);
    echoHeartbeat("Total Pages: $totalPages");
    
    $pdo->prepare("UPDATE pdf_study_jobs SET total_pages = ?, progress = 10 WHERE job_id = ?")->execute([$totalPages, $jobId]);

    $masterMCQs = [];
    $masterFlashcards = [];
    
    // 🧪 5. Chunking Logic: Process 2 pages at a time (smaller for memory safety)
    $chunkSize = 2;
    $processedCount = 0;

    for ($i = 0; $i < $totalPages; $i += $chunkSize) {
        $chunkPages = array_slice($pages, $i, $chunkSize);
        $chunkText = "";
        foreach ($chunkPages as $page) {
            $chunkText .= $page->getText() . "\n\n";
        }

        if (trim($chunkText) === "") {
            echoHeartbeat("Skipping empty chunk at page $i...");
            $processedCount += count($chunkPages);
            continue;
        }

        echoHeartbeat("Calling Gemini for chunk beginning at page $i...");

        // 🤖 Master Study-Pack Prompt (Enforced Schema)
        $prompt = "
            Role: Elite MCQ Architect. Analyze the text and generate a Study Pack.
            Text: " . $chunkText . "
            Rules: Use Bloom's Taxonomy. JSON ONLY. No markdown.
            Schema: {\"status\":\"success\",\"mcqs\":[],\"flashcards\":[]}
        ";

        try {
            $aiResponseText = callGeminiAPI($prompt, ['temperature' => 0.4]);
            $aiResponseText = preg_replace('/```json\s*|\s*```/', '', $aiResponseText);
            $result = json_decode(trim($aiResponseText), true);

            if ($result && isset($result['status']) && $result['status'] === 'success') {
                if (!empty($result['mcqs'])) $masterMCQs = array_merge($masterMCQs, $result['mcqs']);
                if (!empty($result['flashcards'])) $masterFlashcards = array_merge($masterFlashcards, $result['flashcards']);
                echoHeartbeat("Collected " . count($result['mcqs']) . " MCQs and " . count($result['flashcards']) . " Flashcards.");
            }
        } catch (Exception $ae) {
            echoHeartbeat("AI Chunk Error: " . $ae->getMessage());
            // We continue processing other chunks if one fails
        }

        $processedCount += count($chunkPages);
        $progress = floor(10 + ( ($processedCount / $totalPages) * 80 ));
        
        $pdo->prepare("UPDATE pdf_study_jobs SET processed_pages = ?, progress = ? WHERE job_id = ?")
            ->execute([$processedCount, $progress, $jobId]);
            
        usleep(500000); // 0.5s pause to prevent rate limits
    }

    echoHeartbeat("Saving Final Pack...");
    // 💾 6. Save Final Aggregated Pack
    $studyPack = [
        'file_name' => $job['file_name'],
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => "Total MCQs: " . count($masterMCQs) . ", Flashcards: " . count($masterFlashcards),
        'mcqs' => $masterMCQs,
        'flashcards' => $masterFlashcards
    ];

    $pdo->prepare("INSERT INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)")
        ->execute([$jobId, $userId, json_encode($studyPack)]);

    // 🎉 7. Final Success Signal
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100 WHERE job_id = ?")->execute([$jobId]);
    echoHeartbeat("Work Complete! 🏆");

} catch (Exception $e) {
    // ❌ 8. Handle Fatal Failure
    echoHeartbeat("FATAL ERROR: " . $e->getMessage());
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
        ->execute([$e->getMessage(), $jobId]);
}
?>
