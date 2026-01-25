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
    $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : '';
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : 'Medium'; // Default to Medium
    $language = isset($_POST['language']) ? $_POST['language'] : 'English'; // Default to English
    $existingText = isset($_POST['existing_text']) ? $_POST['existing_text'] : '';

    $geminiParts = [];
    $finalExtractedText = "";

    // 3. The "OCR" System Prompt
    $systemPrompt = "You are an expert AI Educator with OCR capabilities. 
    TASK:
    1. READ the text from the provided image, document, or text.
    2. UNDERSTAND the key concepts.
    3. GENERATE 5 multiple-choice questions based on that content.
    
    DIFFICULTY LEVEL: " . $difficulty . "
    TARGET LANGUAGE: " . $language . "
    
    INSTRUCTIONS:
    - If the user selected Marathi, the Questions, Options, and Explanations MUST be in Marathi.
    - If the user selected Hindi, use Hindi.
    - If the user selected English, use English.
    - Translate the content if the source text is in a different language.
    
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
    
    // CASE 0: "Load More" - Use existing text
    if (!empty($existingText)) {
        $finalExtractedText = $existingText;
        $geminiParts[] = ['text' => $systemPrompt . "\n\nCONTEXT TEXT:\n" . $existingText . "\n\nINSTRUCTION: Generate 5 NEW and UNIQUE questions different from any previous ones if possible."];
    }
    // CASE 1: New Input
    elseif ($inputType === 'text') {
        if (empty($_POST['content'])) throw new Exception("No text provided");
        $finalExtractedText = $_POST['content'];
        $geminiParts[] = ['text' => $systemPrompt . "\n\nTEXT SOURCE:\n" . $_POST['content']];

    } elseif ($inputType === 'camera' || $inputType === 'file') {
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed");
        }

        $filePath = $_FILES['file']['tmp_name'];
        $mimeType = mime_content_type($filePath);
        
        // --- CASE A: IMAGES (The True OCR) ---
        if (strpos($mimeType, 'image') !== false) {
            $base64Image = base64_encode(file_get_contents($filePath));
            // Note: For images, we can't easily "extract" text to return without a separate call.
            // So for "Load More" on images, we might rely on Gemini's internal text capability or just re-send image if needed.
            // BETTER STRATEGY: Ask Gemini to ALSO return the extracted text in the JSON? 
            // For now, to keep it simple, we will send the image. 
            // But to support "Load More", we actually need the text. 
            // Workaround: We will ask Gemini to generate questions AND provide a summary of text if possible, but JSON structure is strict.
            // changing strategy: logic for image "Load More" will be handled by re-sending the image if specific text isn't returned, 
            // OR we just accept that for images, "Load More" might need to re-process or we just store context on client? 
            // Let's stick to standard flow: Users usually want "Load More" on text/PDFs. 
            // For images, we will just proceed. Only PDF/Docs give us clear text to return easily.
            
            $geminiParts[] = ['text' => $systemPrompt . "\n\n(Analyze this image and extract the text to create the quiz)"];
            $geminiParts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Image
                ]
            ];
            // We set a placeholder for extracted text for images, as we don't have it raw.
            $finalExtractedText = "[Image Content]"; 
        }
        // --- CASE B: PDF DOCUMENTS ---
        elseif ($mimeType === 'application/pdf') {
            $extractedText = extractTextFromPdf($filePath);
            
            if (strlen(trim($extractedText)) < 10) {
                throw new Exception("OCR Failed: This PDF appears to be a scanned image. Please convert it to JPG/PNG.");
            }
            
            $extractedText = substr($extractedText, 0, 50000); 
            $finalExtractedText = $extractedText;
            $geminiParts[] = ['text' => $systemPrompt . "\n\nPDF TEXT:\n" . $extractedText];
        }
        // --- CASE C: WORD DOCUMENTS ---
        elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'office') !== false) {
            $extractedText = extractTextFromWord($filePath);
            $finalExtractedText = $extractedText;
            $geminiParts[] = ['text' => $systemPrompt . "\n\nDOC TEXT:\n" . substr($extractedText, 0, 50000)];
        }
        // --- CASE D: PLAIN TEXT ---
        elseif ($mimeType === 'text/plain') {
            $extractedText = file_get_contents($filePath);
            $finalExtractedText = $extractedText;
            $geminiParts[] = ['text' => $systemPrompt . "\n\nFILE TEXT:\n" . $extractedText];
        }
        else {
            throw new Exception("Unsupported file type: $mimeType.");
        }
    } else {
        throw new Exception("Invalid input type");
    }

    // 5. Call Gemini API (Smart Model Selection)
    $modelsToTry = ['gemini-2.5-flash'];
    $finalReply = null;
    $lastError = "";

    foreach ($modelsToTry as $model) {
        $payload = [
            "contents" => [["parts" => $geminiParts]],
            // Increased to 4000 to prevent truncation of 5 questions
            "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 4000] 
        ];

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
            if (strpos($errorMsg, 'quota') !== false || strpos($errorMsg, '429') !== false || $httpCode === 429) {
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
    
    // Robust JSON Extraction: Find first '[' and last ']'
    $startPos = strpos($rawText, '[');
    $endPos = strrpos($rawText, ']');
    
    if ($startPos !== false && $endPos !== false) {
        $rawText = substr($rawText, $startPos, $endPos - $startPos + 1);
    }
    
    $quizData = json_decode($rawText, true);

    if (!$quizData) {
        // Fallback: Try cleaning control characters if decode failed
        $rawTextClean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $rawText);
        $quizData = json_decode($rawTextClean, true);
    }

    if (!$quizData) throw new Exception("Failed to generate valid quiz structure. Raw AI Output: " . substr($rawText, 0, 100));

    ob_clean();
    echo json_encode([
        "status" => "success", 
        "data" => $quizData,
        "extracted_text" => $finalExtractedText // Return text for "Load More"
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log("Quiz Gen Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>