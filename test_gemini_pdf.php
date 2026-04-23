<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config/ai_config.php';

// A tiny, valid 1-page PDF (a blank page or simple text) in base64
$pdfBase64 = "JVBERi0xLjQKMSAwIG9iago8PAovVGl0bGUgKPBEdW1teSBQREY+KQovQ3JlYXRvciAoRGVlcE1pbmQpCi9Qcm9kdWNlciAoRGVlcE1pbmQpCj4+CmVuZG9iagoyIDAgb2JqCjw8Ci9UeXBlIC9DYXRhbG9nCi9QYWdlcyAzIDAgUgo+PgplbmRvYmoKMyAwIG9iago8PAovVHlwZSAvUGFnZXMKL0NvdW50IDEKL0tpZHMgWyA0IDAgUiBdCj4+CmVuZG9iago0IDAgb2JqCjw8Ci9UeXBlIC9QYWdlCi9QYXJlbnQgMyAwIFIKL1Jlc291cmNlcyA8PAovRm9udCA8PAovRjEgNSAwIFIKPj4KPj4KL01lZGlhQm94IFsgMCAwIDYxMiA3OTIgXQovQ29udGVudHMgNiAwIFIKPj4KZW5kb2JqCjUgMCBvYmoKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+CmVuZG9iago2IDAgb2JqCjw8Ci9MZW5ndGggNDQKPj4Kc3RyZWFtCkJUCi9GMSAyNCBUZgoxMDAgNzAwIFRkCihIZWxsbyBXb3JsZCkgVGoKRVQKZW5kc3RyZWFtCmVuZG9iagp4cmVmCjAgNwowMDAwMDAwMDAwIDY1NTM1IGYKMDAwMDAwMDAwOSAwMDAwMCBuCjAwMDAwMDAwOTQgMDAwMDAgbgowMDAwMDAwMTQzIDAwMDAwIG4KMDAwMDAwMDIwMSAwMDAwMCBuCjAwMDAwMDAzMTggMDAwMDAgbgowMDAwMDAwNDA2IDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA3Ci9Sb290IDIgMCBSCjovSW5mbyAxIDAgUgo+PgpzdGFydHhyZWYKMDAwMDAwMDUwMQolJUVPRgo=";

echo "Testing Gemini API with PDF...\n";
try {
    $result = callGeminiPDF("What does this PDF say?", $pdfBase64);
    echo "SUCCESS:\n$result\n";
} catch (Exception $e) {
    echo "ERROR:\n" . $e->getMessage() . "\n";
}
?>
