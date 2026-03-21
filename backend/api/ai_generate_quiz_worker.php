<?php
/**
 * AI Quiz Worker Processor
 * This script is called within a background process to handle the actual generation.
 */

require_once '../config/db.php';
require_once 'AiUsageManager.php';
require_once 'AiTaskManager.php';

function processQuizTask($taskId, $payload, $taskManager, $pdo) {
    try {
        $taskManager->updateTask($taskId, 'running', 10);
        
        $userId = isset($payload['user_id']) ? $payload['user_id'] : 0;
        $difficulty = $payload['difficulty'] ?? 'Medium';
        $language = $payload['language'] ?? 'English';
        $inputType = $payload['input_type'] ?? '';
        $filePath = $payload['file_path'] ?? null;
        $mimeType = $payload['mime_type'] ?? null;
        
        // 1. Text Extraction
        $extractedText = $payload['content'] ?? "";
        
        if ($filePath) {
            $taskManager->updateTask($taskId, 'running', 20);
            if (strpos($mimeType, 'image') !== false) {
                // For images, we just use the raw base64 in Gemini
                $base64Image = base64_encode(file_get_contents($filePath));
            } elseif ($mimeType === 'application/pdf') {
                require_once 'ai_helpers.php'; // Use clean helpers
                $extractedText = extractTextFromPdf($filePath);
                $taskManager->updateTask($taskId, 'running', 40);
            }
            // ... (Other file types would need corresponding helpers)
        }

        // 2. Prepare System Prompt
        $systemPrompt = "You are an AI Professional Educator. Task: Generate 10 high-quality MCQs about the provided content. 
        Difficulty: $difficulty. Language: $language. Respond ONLY in valid JSON format:
        [{\"question\":\"\", \"option_a\":\"\", \"option_b\":\"\", \"option_c\":\"\", \"option_d\":\"\", \"correct_answer\":\"a\", \"explanation\":\"\"}]";

        $geminiParts = [["text" => $systemPrompt . "\n\nTEXT:\n" . substr($extractedText, 0, 50000)]];
        if (isset($base64Image)) {
            $geminiParts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Image
                ]
            ];
        }

        // 3. Call AI
        $taskManager->updateTask($taskId, 'running', 60);
        
        if (!defined('GEMINI_API_KEY')) {
            require_once '../config/ai_config.php';
        }

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "contents" => [["parts" => $geminiParts]],
            "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 8000]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode !== 200 || !isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("AI Generation Failed ($httpCode)");
        }

        $finalReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
        $tokensUsed = $decoded['usageMetadata']['totalTokenCount'] ?? 0;

        // 4. Clean and Save Results
        $taskManager->updateTask($taskId, 'running', 100);
        
        $rawText = str_replace(["```json", "```"], "", $finalReply);
        $startPos = strpos($rawText, '[');
        $endPos = strrpos($rawText, ']');
        if ($startPos !== false && $endPos !== false) {
            $rawText = substr($rawText, $startPos, $endPos - $startPos + 1);
        }
        
        $quizData = json_decode($rawText, true);
        if (!$quizData) throw new Exception("AI returned invalid JSON structure.");

        // Log usage
        if ($userId > 0 && $tokensUsed > 0) {
            $aiManager = new AiUsageManager($userId);
            $aiManager->logUsage($tokensUsed);
        }

        $taskManager->updateTask($taskId, 'completed', 100, $quizData);

        // Cleanup temp file
        if ($filePath && file_exists($filePath)) unlink($filePath);

    } catch (Exception $e) {
        $taskManager->updateTask($taskId, 'failed', 0, null, $e->getMessage());
    }
}
?>
