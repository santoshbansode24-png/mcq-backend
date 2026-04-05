<?php
/**
 * Diagnostic PDF Worker for Debugging
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../config/ai_config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

$job_id = $_GET['job_id'] ?? 0;

if (!$job_id) {
    die("Usage: debug_pdf_worker.php?job_id=XXX");
}

echo "<h1>PDF Diagnostic for Job #$job_id</h1>";

// 1. Fetch Job
$stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    die("<p style='color:red;'>Job ID #$job_id not found in database.</p>");
}

echo "<b>File Name:</b> " . htmlspecialchars($job['file_name']) . "<br>";
echo "<b>File Path:</b> " . htmlspecialchars($job['file_path']) . "<br><br>";

// 2. Locate File
$filePath = $job['file_path'];
if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $filePath)) {
    $baseDir = dirname(__DIR__);
    $filePath = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_study' . DIRECTORY_SEPARATOR . basename($filePath);
}

echo "<b>Absolute Path:</b> " . htmlspecialchars($filePath) . "<br>";

if (!file_exists($filePath)) {
    echo "<p style='color:red;'>File does not exist on disk!</p>";
} else {
    echo "<p style='color:green;'>File found on disk. Size: " . filesize($filePath) . " bytes</p>";
}

// 3. Test Extraction
echo "<h2>Step 1: Text Extraction (Smalot)</h2>";
try {
    $parser = new Parser();
    $pdf = $parser->parseFile($filePath);
    $fullText = $pdf->getText();
    $fullText = preg_replace('/\s+/', ' ', $fullText);
    $fullText = trim($fullText);
    
    echo "<b>Extracted Text Length:</b> " . strlen($fullText) . " characters<br>";
    echo "<b>Sample Text (First 300 chars):</b><br>";
    echo "<pre style='background:#f1f5f9; padding:10px; border:1px solid #cbd5e1;'>" . htmlspecialchars(substr($fullText, 0, 300)) . "...</pre>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Extraction Error: " . $e->getMessage() . "</p>";
}

// 4. Test Chunking
echo "<h2>Step 2: Chunking</h2>";
$chunkSize = 5000;
$chunks = str_split($fullText, $chunkSize);
echo "<b>Total Chunks:</b> " . count($chunks) . "<br>";

// 5. Test AI on First Chunk
echo "<h2>Step 3: AI Call (First Chunk)</h2>";
if (count($chunks) > 0) {
    try {
        $chunk = $chunks[0];
        $prompt = "Extract MCQs and Flashcards from this text Chunk. Return JSON ONLY. Text: " . $chunk;
        
        $payload = [
            'contents' => [[ 'parts' => [['text' => $prompt]] ]],
            'generationConfig' => [ 'temperature' => 0.4, 'responseMimeType' => 'application/json' ]
        ];

        echo "<b>Sending request to Gemini...</b><br>";
        
        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<b>HTTP Status:</b> $httpCode<br>";
        if ($httpCode === 200) {
            echo "<p style='color:green;'>AI Call Success!</p>";
            echo "<b>Response Sample:</b><br>";
            echo "<pre style='background:#f0f9ff; padding:10px; border:1px solid #bae6fd;'>" . htmlspecialchars(substr($response, 0, 300)) . "...</pre>";
        } else {
            echo "<p style='color:red;'>AI Call Failed: " . htmlspecialchars($response) . "</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color:red;'>AI Test Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:orange;'>No chunks available to test.</p>";
}
echo "<br><hr><p>Diagnostic Finished. Check details above.</p>";
?>
