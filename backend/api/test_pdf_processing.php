<?php
/**
 * End-to-End PDF Processing Test
 * This script simulates a PDF upload and triggers the AI worker to verify the entire pipeline.
 */
require_once '../config/db.php';
require_once '../config/ai_config.php';

header('Content-Type: text/plain');

echo "🧪 STARTING END-TO-END PDF PROCESSING TEST\n";
echo "=========================================\n\n";

// 1. Pick a sample PDF from the uploads folder
$uploadDir = __DIR__ . '/../uploads/pdf_study/';
$files = glob($uploadDir . '*.pdf');

if (empty($files)) {
    die("❌ Error: No PDF files found in $uploadDir. Please upload a PDF through the app first.\n");
}

$samplePdfPath = $files[0];
$fileName = basename($samplePdfPath);
echo "📄 Using sample PDF: $fileName\n";

// 2. Read PDF bytes and encode to base64
$pdfBytes = file_get_contents($samplePdfPath);
if (!$pdfBytes) die("❌ Error: Could not read $samplePdfPath\n");
$pdfBase64 = base64_encode($pdfBytes);
echo "📦 PDF Base64 size: " . strlen($pdfBase64) . " bytes\n";

// 3. Create a Test Job
try {
    $user_id = 1; // Test user
    $stmt = $pdo->prepare("INSERT INTO pdf_study_jobs (user_id, file_name, file_path, pdf_base64, status, progress) VALUES (?, ?, ?, ?, 'pending', 0)");
    $stmt->execute([$user_id, "TEST_" . $fileName, $fileName, $pdfBase64]);
    $job_id = $pdo->lastInsertId();
    echo "✅ Created Test Job ID: $job_id\n";
} catch (Exception $e) {
    die("❌ Error creating job: " . $e->getMessage() . "\n");
}

// 4. Trigger Worker Manually (Direct include for debugging output)
echo "\n⚙️ TRIGGERING WORKER (Manual Include)...\n";
echo "----------------------------------------\n";

// We set the GET parameter so pdf_worker_ai.php knows which job to pick
$_GET['key'] = WORKER_SECRET;
$_GET['force_job_id'] = $job_id;

ob_start();
include 'pdf_worker_ai.php';
$workerOutput = ob_get_clean();

echo "WORKER OUTPUT:\n$workerOutput\n";
echo "----------------------------------------\n";

// 5. Verify Result
try {
    $stmt = $pdo->prepare("SELECT status, error_message FROM pdf_study_jobs WHERE job_id = ?");
    $stmt->execute([$job_id]);
    $result = $stmt->fetch();
    
    if ($result['status'] === 'completed') {
        echo "\n🎉 TEST SUCCESSFUL! Job completed.\n";
        
        $stmtContent = $pdo->prepare("SELECT LENGTH(study_pack_json) as len FROM pdf_study_content WHERE job_id = ?");
        $stmtContent->execute([$job_id]);
        $content = $stmtContent->fetch();
        echo "📊 Generated Content Size: " . ($content['len'] ?? 0) . " bytes\n";
    } else {
        echo "\n❌ TEST FAILED. Job status: " . $result['status'] . "\n";
        echo "📝 Error Message: " . ($result['error_message'] ?? 'None') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error verifying result: " . $e->getMessage() . "\n";
}

echo "\n--- TEST FINISHED ---\n";
