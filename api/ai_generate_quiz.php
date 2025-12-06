<?php
/**
 * AI Quiz Generator API
 * MCQ Project 2.0
 * 
 * Endpoint: POST /api/ai_generate_quiz.php
 * Purpose: Generate MCQs from chapter content using Gemini AI
 */

require_once '../config/db.php';
require_once '../config/ai_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST requests are allowed', null, 405);
}

// Get JSON input
$input = getJsonInput();
$chapter_id = isset($input['chapter_id']) ? intval($input['chapter_id']) : 0;
$note_id = isset($input['note_id']) ? intval($input['note_id']) : 0;

if ($chapter_id <= 0 && $note_id <= 0) {
    sendResponse('error', 'Valid chapter_id or note_id is required', null, 400);
}

try {
    $content_text = "";

    // Fetch content from database
    if ($note_id > 0) {
        $stmt = $pdo->prepare("SELECT content, title FROM notes WHERE note_id = ?");
        $stmt->execute([$note_id]);
        $note = $stmt->fetch();
        if ($note) {
            $content_text = $note['content'];
        }
    } else {
        // Fetch first note content for the chapter
        $stmt = $pdo->prepare("SELECT content, title FROM notes WHERE chapter_id = ? AND content IS NOT NULL LIMIT 1");
        $stmt->execute([$chapter_id]);
        $note = $stmt->fetch();
        if ($note) {
            $content_text = $note['content'];
        }
    }

    if (empty($content_text)) {
        // Fallback: If no text content, try to use chapter name and subject to generate generic questions
        // This is a fallback if PDF parsing isn't available
        $stmt = $pdo->prepare("
            SELECT c.chapter_name, s.subject_name 
            FROM chapters c 
            JOIN subjects s ON c.subject_id = s.subject_id 
            WHERE c.chapter_id = ?
        ");
        $stmt->execute([$chapter_id]);
        $info = $stmt->fetch();
        if ($info) {
            $content_text = "Subject: " . $info['subject_name'] . ". Chapter: " . $info['chapter_name'] . ".";
        } else {
            sendResponse('error', 'No content found to generate quiz', null, 404);
        }
    }

    // Construct Prompt for Gemini
    $prompt = "Generate 5 multiple choice questions based on the following text. 
    Return ONLY a JSON array. Each object in the array must have:
    - 'question': The question text
    - 'option_a': Option A
    - 'option_b': Option B
    - 'option_c': Option C
    - 'option_d': Option D
    - 'correct_answer': The correct option letter (a, b, c, or d)
    - 'explanation': A brief explanation of the answer.
    
    Text: " . substr($content_text, 0, 2000); // Limit text length for API

    // Call Gemini API
    $ai_response = callGeminiAPI($prompt);

    if (!$ai_response) {
        sendResponse('error', 'Failed to generate quiz from AI', null, 500);
    }

    // Clean up response to ensure valid JSON
    $json_start = strpos($ai_response, '[');
    $json_end = strrpos($ai_response, ']');
    
    if ($json_start !== false && $json_end !== false) {
        $json_str = substr($ai_response, $json_start, $json_end - $json_start + 1);
        $quiz_data = json_decode($json_str, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            sendResponse('success', 'Quiz generated successfully', $quiz_data, 200);
        } else {
            sendResponse('error', 'AI returned invalid JSON format', ['raw' => $ai_response], 500);
        }
    } else {
        sendResponse('error', 'AI response format error', ['raw' => $ai_response], 500);
    }

} catch (PDOException $e) {
    sendResponse('error', 'Database error occurred', ['error' => $e->getMessage()], 500);
}
?>
