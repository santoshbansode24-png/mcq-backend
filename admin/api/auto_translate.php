<?php
/**
 * Admin API - Gemini AI Auto Translation (English -> Hindi & Marathi)
 * Veeru
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db.php';
require_once '../../backend/config/ai_config.php';

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$texts = $input['texts'] ?? null;
if (!$texts && isset($input['text'])) {
    $texts = [$input['text']];
}

if (!$texts || !is_array($texts)) {
    echo json_encode(['status' => 'error', 'message' => 'No texts provided for translation.']);
    exit();
}

$prompt = "You are an expert translator specializing in Indian school educational content (CBSE / State Board).
Translate the following array of English texts into BOTH native Hindi (हिंदी) and native Marathi (मराठी).
Keep educational and scientific terminology natural and accurate for school students.

Return ONLY a valid JSON object matching this structure without markdown formatting:
{
  \"translations\": [
    {
      \"original\": \"...\",
      \"hi\": \"...\",
      \"mr\": \"...\"
    }
  ]
}

Input Texts:
" . json_encode($texts, JSON_UNESCAPED_UNICODE);

try {
    $response = callGeminiAPI($prompt, [
        'temperature' => 0.2,
        'responseMimeType' => 'application/json'
    ]);

    // Clean JSON response from Gemini
    $cleanJson = preg_replace('/^```json\s*|\s*```$/i', '', trim($response));
    $parsed = json_decode($cleanJson, true);

    if (isset($parsed['translations']) && is_array($parsed['translations'])) {
        echo json_encode([
            'status' => 'success',
            'translations' => $parsed['translations']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to parse AI translation output.',
            'raw' => $response
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
