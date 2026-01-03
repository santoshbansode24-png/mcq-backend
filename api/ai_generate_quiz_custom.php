<?php
// 1. Setup & Performance Headers
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/ai_config.php';
// Ensure you ran: composer require smalot/pdfparser phpoffice/phpword
require_once '../../vendor/autoload.php'; 

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

// --- OCR & Text Extraction Helpers ---

function extractTextFromPdf($filePath) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        // Clean up weird PDF spacing
        return preg_replace('/\s+/', ' ', $text);
    } catch (Exception $e) {
        return "";
    }
}

function extractTextFromWord($filePath) {
    try {
        $phpWord = IOFactory::load($filePath);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . " ";
                }
            }
        }
        return $text;
    } catch (Exception $e) {
        return "";
    }
}

try {
    // 2. Validate Inputs
    if (!isset($_POST['input_type'])) throw new Exception("Missing input_type");

    $inputType = $_POST['input_type'];
    $geminiParts = [];
    
    // 3. The "OCR" System Prompt
    // We tell Gemini explicitly to act as an OCR reader first, then a Quiz Generator.
    $systemPrompt = "You are an expert AI Educator with OCR capabilities. 
    TASK:
    1. READ the text from the provided image, document, or text.
    2. UNDERSTAND the key concepts.
    3. GENERATE 5 multiple-choice questions based on that content.
    
    OUTPUT FORMAT (Strict JSON):
    [
        {
            \"question\": \"Question text\",
            \"option_a\": \"Option A\",
            \"option_b\": \"Option B\",
            \"option_c\": \"Option C\",
            \"option_d\": \"Option D\",
            \"correct_answer\": \"a\",
            \"explanation\": \"Why this is correct\"
        }
    ]
    Return ONLY JSON. No markdown.";

    // 4. Handle Inputs (OCR Logic)
    
    if ($inputType === 'text') {
        if (empty($_POST['content'])) throw new Exception("No text provided");
        $geminiParts[] = ['text' => $systemPrompt . "\n\nTEXT SOURCE:\n" . $_POST['content']];

    } elseif ($inputType === 'camera' || $inputType === 'file') {
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed");
        }

        $filePath = $_FILES['file']['tmp_name'];
        $mimeType = mime_content_type($filePath);
        
        // --- CASE A: IMAGES (The True OCR) ---
        // If it's an image, we send the visual data. Gemini acts as the OCR reader.
        if (strpos($mimeType, 'image') !== false) {
            $base64Image = base64_encode(file_get_contents($filePath));
            $geminiParts[] = ['text' => $systemPrompt . "\n\n(Analyze this image and extract the text to create the quiz)"];
            $geminiParts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Image
                ]
            ];
        }
        // --- CASE B: PDF DOCUMENTS ---
        elseif ($mimeType === 'application/pdf') {
            $extractedText = extractTextFromPdf($filePath);
            
            // Smart Check: If PDF has no text (it's scanned), return specific error
            if (strlen(trim($extractedText)) < 10) {
                throw new Exception("OCR Failed: This PDF appears to be a scanned image. Please convert it to JPG/PNG or take a screenshot so the AI can read it visually.");
            }
            
            // Truncate to avoid token limits (approx 10k words)
            $extractedText = substr($extractedText, 0, 50000); 
            $geminiParts[] = ['text' => $systemPrompt . "\n\nPDF TEXT:\n" . $extractedText];
        }
        // --- CASE C: WORD DOCUMENTS ---
        elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'office') !== false) {
            $extractedText = extractTextFromWord($filePath);
            $geminiParts[] = ['text' => $systemPrompt . "\n\nDOC TEXT:\n" . substr($extractedText, 0, 50000)];
        }
        // --- CASE D: PLAIN TEXT ---
        elseif ($mimeType === 'text/plain') {
            $extractedText = file_get_contents($filePath);
            $geminiParts[] = ['text' => $systemPrompt . "\n\nFILE TEXT:\n" . $extractedText];
        }
        else {
            throw new Exception("Unsupported file type: $mimeType.");
        }
    }

    // 5. Call Gemini API (Smart Model Selection)
    // We try gemini-2.5-flash first (Confirmed working model)
    $modelsToTry = ['gemini-2.5-flash'];
    $finalReply = null;
    $lastError = "";

    foreach ($modelsToTry as $model) {
        $payload = [
            "contents" => [["parts" => $geminiParts]],
            "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 2000]
        ];

        // Use the model specific URL
        $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . GEMINI_API_KEY;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode == 200 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $finalReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
            break; 
        } else {
            $errorMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $response;
            
            // Check for Quota Limit (429)
            if (strpos($errorMsg, 'quota') !== false || strpos($errorMsg, '429') !== false || $httpCode === 429) {
                // Return a clean error immediately
                ob_clean();
                echo json_encode([
                    "status" => "error", 
                    "message" => "⚠️ AI Overload: The free AI quota limit is reached. Please wait 1 minute and try again."
                ]);
                exit;
            }
            
            $lastError = "Model $model failed ($httpCode): " . $errorMsg;
            error_log($lastError); 
        }
    }

    if (!$finalReply) throw new Exception("AI Processing Failed. Details: " . $lastError);

    // 6. Clean and Output JSON
    $rawText = str_replace(["```json", "```"], "", $finalReply);
    
    // Extract JSON using regex if AI chats a bit
    if (preg_match('/\[.*\]/s', $rawText, $matches)) {
        $rawText = $matches[0];
    }
    
    $quizData = json_decode($rawText, true);

    if (!$quizData) throw new Exception("Failed to generate valid quiz structure. Raw AI Output: " . substr($rawText, 0, 100));

    ob_clean();
    echo json_encode(["status" => "success", "data" => $quizData]);

} catch (Exception $e) {
    ob_clean();
    error_log("Quiz Gen Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>