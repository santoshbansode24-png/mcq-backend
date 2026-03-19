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

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }


// --- GLOBAL ERROR CATCHING ---
try {
    // Performance Tuning
    ini_set('memory_limit', '512M');
    ini_set('upload_max_filesize', '100M');
    ini_set('post_max_size', '100M');
    set_time_limit(300);
    ini_set('display_errors', 0); // Important: Keep off to prevent raw error text in JSON
    error_reporting(E_ALL);


    // --- DEPENDENCY CHECK ---
    if (!extension_loaded('mbstring')) {
        throw new Exception("Server Error: PHP mbstring extension is missing.");
    }

// Load AI config safely
if (file_exists('../config/ai_config.php')) {
    require_once '../config/ai_config.php';
} else {
    // Railway: read from environment variables
    if (!defined('GEMINI_API_KEY')) {
        $envKey = getenv('GEMINI_API_KEY');
        if ($envKey) define('GEMINI_API_KEY', $envKey);
    }
    if (!defined('GEMINI_API_URL')) {
        define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
    }
}

// Ensure you ran: composer require smalot/pdfparser phpoffice/phpword
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// --- AUTH & TRAFFIC CONTROL ---
require_once 'AiUsageManager.php';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0; 

// Check limits BEFORE calling Gemini
if ($userId > 0) {
    $aiManager = new AiUsageManager($userId);
    $canProceed = $aiManager->canMakeRequest();
    if ($canProceed !== true) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $canProceed]);
        exit;
    }
}

// --- OCR & Text Extraction Helpers ---

function extractTextFromPdf($filePath) {
    if (!class_exists('Smalot\PdfParser\Parser')) {
        throw new Exception("PDF Parser library not found. Run 'composer require smalot/pdfparser'");
    }
    try {
        $parser = new Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        
        // --- OPTIMIZATION: Extract only first 10 pages to avoid timeouts ---
        $pages = $pdf->getPages();
        $text = '';
        $pageCount = min(count($pages), 10);
        for ($i = 0; $i < $pageCount; $i++) {
            $text .= $pages[$i]->getText() . " ";
        }
        
        return preg_replace('/\s+/', ' ', $text);
    } catch (Throwable $e) {
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

    // 2. Validate Inputs

    // Check for potential post_max_size overflow (POST is empty but Content-Length exists)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        throw new Exception("The request was too large for the server. Content-Length: " . $_SERVER['CONTENT_LENGTH'] . " bytes.");
    }

    $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : '';
    $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : 'Medium';
    $language = isset($_POST['language']) ? $_POST['language'] : 'English';
    $existingText = isset($_POST['existing_text']) ? $_POST['existing_text'] : '';

    $geminiParts = [];
    $finalExtractedText = "";

    // 3. The "OCR" System Prompt
    $systemPrompt = "You are an expert AI Educator with OCR capabilities. 
    TASK:
    1. READ the text from the provided image, document, or text.
    2. UNDERSTAND the key concepts.
    3. GENERATE 10 multiple-choice questions based on that content.
    
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
        $geminiParts[] = ['text' => $systemPrompt . "\n\nCONTEXT TEXT:\n" . $existingText . "\n\nINSTRUCTION: Generate 10 NEW and UNIQUE questions different from any previous ones if possible."];
    }
    // CASE 1: New Input
    elseif ($inputType === 'text') {
        if (empty($_POST['content'])) throw new Exception("No text provided");
        $finalExtractedText = $_POST['content'];
        $geminiParts[] = ['text' => $systemPrompt . "\n\nTEXT SOURCE:\n" . $_POST['content']];

    } elseif ($inputType === 'camera' || $inputType === 'file') {
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
            $errMessage = "File upload failed: ";
            switch ($errCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errMessage .= "The document is too large. Please upload a smaller file.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errMessage .= "The upload was interrupted. Please try again.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errMessage .= "No file was uploaded.";
                    break;
                default:
                    $errMessage .= "Server-side error. Please check your internet and try again.";
                    break;
            }
            throw new Exception($errMessage);
        }

        $filePath = $_FILES['file']['tmp_name'];
        $mimeType = mime_content_type($filePath);
        
        // --- CASE A: IMAGES (The True OCR) ---
        if (strpos($mimeType, 'image') !== false) {
            $base64Image = base64_encode(file_get_contents($filePath));
            
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

    // 5. Call Gemini API - with Model Fallback for Stability
    $modelsToTry = ['gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-flash'];
    $finalReply = null;
    $lastError = "";

    foreach ($modelsToTry as $model) {
        $payload = [
            "contents" => [["parts" => $geminiParts]],
            "generationConfig" => [
                "temperature" => 0.4, 
                "maxOutputTokens" => 8000,
                // Add topP and topK for additional stability
                "topP" => 0.8,
                "topK" => 40
            ],
            // PERMANENT SOLUTION: Disable safety filters to prevent random blocking of educational content
            "safetySettings" => [
                ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
            ]
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . GEMINI_API_KEY;

        // RETRY LOGIC for 429 Rate Limits
        $maxRetries = 2;
        $retryCount = 0;
        
        while ($retryCount <= $maxRetries) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes for complex generation


            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $decoded = json_decode($response, true);

            if ($httpCode == 200 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $finalReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
                $tokensUsed = isset($decoded['usageMetadata']['totalTokenCount']) ? $decoded['usageMetadata']['totalTokenCount'] : 0;
                break 2; // Success! Exit both loops
            }
 else {
                $errorMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $response;
                
                // If it's a Rate Limit (429) or Server Error (500/503), WAIT and RETRY
                if (($httpCode === 429 || $httpCode >= 500) && $retryCount < $maxRetries) {
                    $retryCount++;
                    sleep(1); // Wait 1 second before retrying
                    continue;
                }

                // If quota is exhausted on this model, try the NEXT model
                if (strpos($errorMsg, 'quota') !== false || strpos($errorMsg, '429') !== false || $httpCode === 429) {
                    $lastError = "Quota exceeded for $model. Trying fallback...";
                    error_log($lastError);
                    break; // Exit inner loop to try next model
                }

                $lastError = "Model $model failed ($httpCode): " . $errorMsg;
                error_log($lastError);
                break; // Try next model 
            }
        }
    }

    if (!$finalReply) throw new Exception("AI Processing Failed. Details: " . $lastError);
    // --- TRACK USAGE ---
    if ($finalReply && $userId > 0 && $tokensUsed > 0) {
        $aiManager->logUsage($tokensUsed);
    }

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

    if (ob_get_level()) ob_end_clean();
    echo json_encode([
        "status" => "success", 
        "data" => $quizData,
        "extracted_text" => $finalExtractedText 
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(200); // CRITICAL: Stop the 500 error, show JSON instead
    echo json_encode([
        "status" => "error", 
        "message" => "AI System Error: " . $e->getMessage(),
        "details" => "File: " . basename($e->getFile()) . " Line: " . $e->getLine(),
        "trace" => substr($e->getTraceAsString(), 0, 200) . "..."
    ]);
}

?>