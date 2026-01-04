<?php
// Script to upload a dummy PDF to test PDF parsing failure
$url = 'http://localhost/mcq%20project2.0/backend/api/ai_generate_quiz_custom.php';
$testPdfPath = __DIR__ . '/test.pdf';

// create dummy PDF if not exists
if (!file_exists($testPdfPath)) {
    // Minimal PDF header
    file_put_contents($testPdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Contents 4 0 R/Parent 2 0 R>>endobj 4 0 obj<</Length 44>>stream\nBT /F1 24 Tf 100 700 Td (Hello World Test PDF) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000010 00000 n\n0000000060 00000 n\n0000000117 00000 n\n0000000216 00000 n\ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n312\n%%EOF");
}

$cfile = new CURLFile($testPdfPath, 'application/pdf', 'test.pdf');
$data = ['input_type' => 'file', 'file' => $cfile];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if ($response === false) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo "Response:\n" . $response;
}
curl_close($ch);
?>
