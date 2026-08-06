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

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/../config/ai_config.php';

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
    $prompt = "Role: You are Veeru Lens, an Expert Educational Content Creator specializing in Active Recall, Spaced Repetition, and rigorous assessment. Your absolute priority is high-quality information extraction. Do not summarize; extract and transform.
        
    Objective: Analyze Section $currentSectionNum of $totalChunks of this document. Your goal is to convert factual, static, and conceptual data into BOTH MCQs AND 'Deep-Scan' Flashcards, and Smart Notes.
    
    SECTION 1: THE \"DEEP-SCAN\" FLASHCARD PROTOCOL (QUESTION & ANSWER FORMAT)
    - Your ABSOLUTE priority is to create flashcards in a clear 'question' and 'answer' format.
    - NO SINGLE WORD QUESTIONS: Never use a single word as a question. Every flashcard question MUST be a complete, grammatically correct sentence.
    - STRICT DISTRIBUTION RATIO: You MUST maintain the following distribution in your output:
       1. 35% VERY SHORT ANSWER TYPE (Full questions requiring a precise 1-3 word answer).
       2. 35% SHORT ANSWER TYPE (Full questions needing a clear explanatory sentence).
       3. 30% FILL IN THE BLANKS (A complete sentence using ________ for the missing concept).
    - Coverage rules: 
       1. Extract all Definitions: Technical terms must have a definition card.
       2. Static Data: Capture dates, names, formulas, and specific figures.
       3. Basic Details: Cover foundational 'What', 'Why', and 'How'.
    - ATOMIC CLARITY: Each card MUST cover exactly ONE single concept. Format: {\"question\": \"Full Question Sentence?\", \"answer\": \"Full Answer Sentence or Phrase\"}.
    - RELEVANCE FILTER: Do NOT create questions from page numbers, footers, headers, or irrelevant decorative text. Focus exclusively on core educational content and high-value facts that a student actually needs to learn.
    - QUALITY AND EXHAUSTIVE EXTRACTION: Do not generate 'filler' questions, but do NOT miss a single important fact. Generate a flashcard for EVERY SINGLE piece of information, concept, definition, and fact present in the text to ensure 100% coverage.        
    
    SECTION 2: CONTENT LOAD BALANCING & DIFFICULTY
    - 1:1 BALANCE RATIO: Maintain a strict 1:1 balance between MCQs and Flashcards. For every concept or fact you convert into a Flashcard, you must also generate a corresponding high-quality MCQ. They must be generated at the exact same level of abundance.
    - All three categories (mcqs, flashcards, and notes) are strictly mandatory and MUST be fully populated. Do not leave notes or any other section empty.
    - $difficultyStr
    
    SECTION 3: HIGH-VOLUME MCQ GENERATION & QUALITY STANDARDS
    - MAXIMIZE MCQ COUNT: You MUST generate as many relevant MCQs as possible from the provided text. Do not stop at just 5 or 10. Extract every single testable concept, fact, date, formula, and definition into a separate MCQ. Exhaustive coverage is your primary goal here.
    - Stem Length: Ensure question stems are meaningful and concise; avoid 'fluff' or irrelevant info.
    - Option Uniformity: All 4 options MUST be of roughly equal length. Never make the correct answer significantly longer than distractors.
    - Plausible Distractors: Distractors must be closely related to the topic and appear technically correct to non-experts. Avoid 'funny' or obviously wrong options.
    - Academic Language: Use plain, easy-to-understand language. Avoid unnecessarily complex jargon or 'tricky' phrasing.
    - Grammatical Matching: All options must match the stem's grammar perfectly to avoid giving away the answer via grammatical clues.
    - The explanation ('e') must concisely educate the student on WHY the correct answer is right and WHY distractors are incorrect.
    
    SECTION 4: SMART NOTES
    - Extract short, highly scannable bullet points across three explicit categories:
       1. definitions: Only core terminology and its meaning.
       2. key_facts: Essential dates, numbers, formulas, or unarguable static truths.
       3. core_concepts: Short explanations of 'how' or 'why' things work.
    - EXHAUSTIVE EXTRACTION: Do not limit to 3-5 points. Generate as many bullet points as needed to capture 100% of the vital information in the text. Do not miss a single concept or fact.
    
    CRITICAL RULES:
    1. STRICT NATIVE LANGUAGE MATCH: If the source text is written in Marathi, EVERY SINGLE output (questions, options, explanations, flashcards) MUST be in Marathi. If the source text is Hindi, output MUST be Hindi. If the source text is English, output MUST be English.
    2. FORMAT: Return ONLY a valid JSON object. No markdown wrappers.
    3. CRITICAL MINIMUM QUOTA: You MUST generate a minimum of 3 MCQs, 3 Flashcards, and 3 bullet points for Notes, regardless of how short the text is. If necessary, infer logical educational concepts. NEVER return an empty array for any category.
    
    SCHEMA:
    {
      \"mcqs\": [
        {\"q\": \"Question\", \"o\": [\"Option 1\", \"Option 2\", \"Option 3\", \"Option 4\"], \"a\": 0, \"e\": \"Explanation why answer is correct\"}
      ],
      \"flashcards\": [
        {\"question\": \"Question text or Fill-in-the-blank\", \"answer\": \"Answer text\"}
      ],
      \"notes\": {
        \"definitions\": [\"Def 1\", \"Def 2\"],
        \"key_facts\": [\"Fact 1\", \"Fact 2\"],
        \"core_concepts\": [\"Concept 1\", \"Concept 2\"]
      }
    }
    
    TEXT TO PROCESS (Section $currentSectionNum of $totalChunks):
    " . $targetChunkText;

    $aiResponse = callGeminiAPI($prompt, [
        'temperature' => 0.3,
        'maxOutputTokens' => 8192,
        'responseMimeType' => 'application/json'
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

    // Fix Control Character Errors (Unescaped newlines/tabs inside strings)
    $cleanJson = str_replace(["\r", "\n", "\t"], " ", $cleanJson);

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

} catch (Throwable $e) {
    error_log("Generate Next Chunk Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
