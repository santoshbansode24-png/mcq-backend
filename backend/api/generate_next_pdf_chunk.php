<?php
/**
 * Veeru Lens: Scan Next Section (Chunk Generation)
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db.php';
require_once '../config/ai_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = intval($input['job_id'] ?? ($_POST['job_id'] ?? 0));
    $userId = intval($input['user_id'] ?? ($_POST['user_id'] ?? 0));

    if ($jobId <= 0) {
        throw new Exception("Invalid job_id provided.");
    }

    // 1. Fetch Job and Extraction Details
    $stmt = $pdo->prepare("SELECT * FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        throw new Exception("Job not found.");
    }

    if ($job['status'] !== 'completed') {
        throw new Exception("Initial PDF scan is not yet completed. Please wait.");
    }

    $extractedText = $job['extracted_text'] ?? '';
    $lastProcessedChunk = intval($job['last_processed_chunk'] ?? 0);
    $totalChunks = intval($job['total_chunks'] ?? 1);

    if (empty($extractedText)) {
        throw new Exception("No text found for this document.");
    }

    if ($lastProcessedChunk >= $totalChunks) {
        echo json_encode(['status' => 'success', 'message' => 'All sections of this document have been fully processed.', 'completed' => true]);
        exit;
    }

    // 2. Extract the Target Chunk
    $words = explode(' ', $extractedText);
    $chunkSize = max(1, ceil(count($words) / $totalChunks));
    $chunks = array_chunk($words, $chunkSize);
    
    if (!isset($chunks[$lastProcessedChunk])) {
         throw new Exception("Chunk out of bounds.");
    }

    $targetChunkText = implode(' ', $chunks[$lastProcessedChunk]);
    $currentSectionNum = $lastProcessedChunk + 1;

    // 3. Setup Difficulty
    $difficulty = $job['difficulty'] ?? 'mix';
    $difficultyStr = "DIFFICULTY LEVEL: MIX (Default)";
    if ($difficulty === 'easy') $difficultyStr = "DIFFICULTY LEVEL: EASY";
    elseif ($difficulty === 'moderate') $difficultyStr = "DIFFICULTY LEVEL: MODERATE";
    elseif ($difficulty === 'hard') $difficultyStr = "DIFFICULTY LEVEL: HARD";

    // 4. Send Chunk to Gemini API
    $prompt = "Role: You are Veeru Lens, an Expert Educational Content Creator.
    Objective: Analyze Section $currentSectionNum of $totalChunks of this document. Convert factual data into MCQs, Deep-Scan Flashcards, and Smart Notes.

    SECTION 1: FLASHCARDS
    - Extract definitions, dates, and facts. Create clear Question-Answer pairs.
    
    SECTION 2: MULTIPLE CHOICE QUESTIONS
    - Extract testable concepts into MCQs. Ensure distractors are plausible.
    - $difficultyStr
    
    SECTION 3: SMART NOTES
    - Extract short bullet points across definitions, key_facts, and core_concepts.

    CRITICAL RULES:
    1. Output strictly in JSON. No markdown wrappers.
    
    SCHEMA:
    {
      \"mcqs\": [
        {\"q\": \"Question\", \"o\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"], \"a\": 0, \"e\": \"Explanation\"}
      ],
      \"flashcards\": [
        {\"question\": \"Full Question Sentence?\", \"answer\": \"Full Answer\"}
      ],
      \"notes\": {
        \"definitions\": [\"Def 1\"],
        \"key_facts\": [\"Fact 1\"],
        \"core_concepts\": [\"Concept 1\"]
      }
    }
    
    TEXT TO PROCESS (Section $currentSectionNum of $totalChunks):
    " . $targetChunkText;

    $aiResponse = callGeminiAPI($prompt, [
        'temperature' => 0.3,
        'maxOutputTokens' => 8192
    ]);
    
    // 5. Clean JSON
    $aiResponse = trim(preg_replace('/^```(?:json)?|```$/mi', '', $aiResponse));
    $jsonStart = strpos($aiResponse, '{');
    $jsonEnd = strrpos($aiResponse, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false) {
        $cleanJson = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
    } elseif ($jsonStart !== false) {
        // Truncated at end
        $cleanJson = substr($aiResponse, $jsonStart);
    } else {
        throw new Exception("AI response did not contain valid JSON structure.");
    }

    $newData = json_decode($cleanJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // SURGICAL REPAIR for truncated JSON
        $repaired = $cleanJson;
        
        // Remove trailing incomplete property/value markers
        $repaired = rtrim($repaired, ", \n\r\t");
        
        // If it ends inside a string, close the string
        $quotesCount = preg_match_all('/(?<!\\\\)"/', $repaired);
        if ($quotesCount % 2 != 0) {
            $repaired .= '"';
        }

        // Close open brackets and braces
        $openBraces   = substr_count($repaired, '{') - substr_count($repaired, '}');
        $openBrackets = substr_count($repaired, '[') - substr_count($repaired, ']');
        
        for ($i = 0; $i < $openBrackets; $i++) $repaired .= ']';
        for ($i = 0; $i < $openBraces;   $i++) $repaired .= '}';

        $newData = json_decode($repaired, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("AI failed to return valid JSON and repair failed.");
        }
    }

    // 6. Merge with Existing Content
    $stmtContent = $pdo->prepare("SELECT * FROM pdf_study_content WHERE job_id = ? LIMIT 1");
    $stmtContent->execute([$jobId]);
    $existingContentRow = $stmtContent->fetch();

    if ($existingContentRow) {
        $existingData = json_decode($existingContentRow['study_pack_json'], true);
        
        // Merge arrays safely
        if (isset($newData['mcqs'])) {
            $existingData['mcqs'] = array_merge($existingData['mcqs'] ?? [], $newData['mcqs']);
        }
        if (isset($newData['flashcards'])) {
            $existingData['flashcards'] = array_merge($existingData['flashcards'] ?? [], $newData['flashcards']);
        }
        if (isset($newData['notes'])) {
            if (!isset($existingData['notes'])) $existingData['notes'] = [];
            
            $existingData['notes']['definitions'] = array_merge($existingData['notes']['definitions'] ?? [], $newData['notes']['definitions'] ?? []);
            $existingData['notes']['key_facts'] = array_merge($existingData['notes']['key_facts'] ?? [], $newData['notes']['key_facts'] ?? []);
            $existingData['notes']['core_concepts'] = array_merge($existingData['notes']['core_concepts'] ?? [], $newData['notes']['core_concepts'] ?? []);
        }

        $mergedJson = json_encode($existingData);

        // Save merged back to DB
        $updateStmt = $pdo->prepare("UPDATE pdf_study_content SET study_pack_json = ? WHERE content_id = ?");
        $updateStmt->execute([$mergedJson, $existingContentRow['content_id']]);
    } else {
        // Just insert it if none exists (rare)
        $insertStmt = $pdo->prepare("INSERT INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)");
        $insertStmt->execute([$jobId, $userId, json_encode($newData)]);
    }

    // 7. Increment Chunk Tracker
    $nextChunk = $lastProcessedChunk + 1;
    $pdo->prepare("UPDATE pdf_study_jobs SET last_processed_chunk = ? WHERE job_id = ?")
        ->execute([$nextChunk, $jobId]);

    echo json_encode([
        'status' => 'success', 
        'message' => "Successfully processed section $currentSectionNum of $totalChunks.",
        'current_chunk' => $nextChunk,
        'total_chunks' => $totalChunks,
        'completed' => ($nextChunk >= $totalChunks)
    ]);

} catch (Exception $e) {
    error_log("Generate Next Chunk Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
