<?php
/**
 * PDF-to-Exam Background AI Worker
 * This script processes one pending PDF study job at a time.
 * Designed to be run via Cron every 1 minute.
 */

set_time_limit(600); // 10 minutes max for large PDFs
ignore_user_abort(true);

require_once '../config/db.php';
require_once '../config/ai_config.php';
require_once '../../vendor/autoload.php';

use Smalot\PdfParser\Parser;

// 🧠 1. Find a pending job
$stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE status = 'pending' LIMIT 1");
$stmt->execute();
$job = $stmt->fetch();

if (!$job) {
    // No work to do
    exit();
}

$jobId = $job['job_id'];
$userId = $job['user_id'];
$filePath = '../../uploads/pdf_study/' . $job['file_path'];

try {
    // 🚦 2. Signal that we are starting
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'processing', progress = 5 WHERE job_id = ?")->execute([$jobId]);

    if (!file_exists($filePath)) {
        throw new Exception("PDF file not found at $filePath");
    }

    // 📖 3. Parse PDF Text
    $parser = new Parser();
    $pdf = $parser->parseFile($filePath);
    $pages = $pdf->getPages();
    $totalPages = count($pages);
    
    $pdo->prepare("UPDATE pdf_study_jobs SET total_pages = ?, progress = 10 WHERE job_id = ?")->execute([$totalPages, $jobId]);

    $masterMCQs = [];
    $masterFlashcards = [];
    
    // Chunking Logic: Process 3 pages at a time
    $chunkSize = 3;
    $processedCount = 0;

    for ($i = 0; $i < $totalPages; $i += $chunkSize) {
        $chunkPages = array_slice($pages, $i, $chunkSize);
        $chunkText = "";
        foreach ($chunkPages as $page) {
            $chunkText .= $page->getText() . "\n\n";
        }

        if (trim($chunkText) === "") {
            $processedCount += count($chunkPages);
            continue;
        }

        // 🤖 4. Call AI with our "Master Study-Pack Prompt"
        $prompt = "
            Role: Elite Educational Content Architect and Senior MCQ Psychometrician.
            Text Chunk: " . $chunkText . "
            
            OBJECTIVE: Analyze the text and generate as many high-quality MCQs and Flashcards as possible.
            
            RULES:
            1. SEMANTIC FILTERING: If the text is junk (TOC, ads, index), return { \"status\": \"no_content\" }.
            2. MCQS: Use Bloom's Taxonomy. No 'All of the above'. Realistic distractors. Include 'Rationale'.
            3. FLASHCARDS: Front (Question/Concept), Back (Concise Answer).
            4. FORMAT: Return ONLY a raw JSON object matching the schema below.
            
            SCHEMA:
            {
              \"status\": \"success\",
              \"mcqs\": [
                { \"question\": \"...\", \"options\": [\"A\",\"B\",\"C\",\"D\"], \"correct_answer\": \"...\", \"explanation\": \"...\", \"difficulty\": \"Medium\" }
              ],
              \"flashcards\": [
                { \"front\": \"...\", \"back\": \"...\", \"topic\": \"...\" }
              ]
            }
        ";

        $aiResponseText = callGeminiAPI($prompt, [
            'temperature' => 0.4,
            'maxOutputTokens' => 2048
        ]);

        // Clean JSON if AI added markdown backticks
        $aiResponseText = preg_replace('/```json\s*|\s*```/', '', $aiResponseText);
        $result = json_decode(trim($aiResponseText), true);

        if ($result && isset($result['status']) && $result['status'] === 'success') {
            if (!empty($result['mcqs'])) {
                $masterMCQs = array_merge($masterMCQs, $result['mcqs']);
            }
            if (!empty($result['flashcards'])) {
                $masterFlashcards = array_merge($masterFlashcards, $result['flashcards']);
            }
        }

        $processedCount += count($chunkPages);
        $progress = floor(10 + ( ($processedCount / $totalPages) * 80 )); // 10% to 90%
        
        $pdo->prepare("UPDATE pdf_study_jobs SET processed_pages = ?, progress = ? WHERE job_id = ?")
            ->execute([$processedCount, $progress, $jobId]);
            
        // Wait 1 second to avoid hitting rate limits too hard
        sleep(1);
    }

    // 💾 5. Save Final Aggregated Pack
    $studyPack = [
        'file_name' => $job['file_name'],
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => "Study Pack generated from " . $totalPages . " pages. Total MCQs: " . count($masterMCQs) . ", Flashcards: " . count($masterFlashcards),
        'mcqs' => $masterMCQs,
        'flashcards' => $masterFlashcards
    ];

    $studyPackJson = json_encode($studyPack);

    $stmt = $pdo->prepare("INSERT INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)");
    $stmt->execute([$jobId, $userId, $studyPackJson]);

    // 🎉 6. Complete Job
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'completed', progress = 100 WHERE job_id = ?")->execute([$jobId]);

} catch (Exception $e) {
    // ❌ 7. Handle Failure
    $pdo->prepare("UPDATE pdf_study_jobs SET status = 'failed', error_message = ? WHERE job_id = ?")
        ->execute([$e->getMessage(), $jobId]);
}
?>
