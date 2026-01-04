<?php
/**
 * Import Vocab from JSON
 * Wipes existing table and imports new words
 */

require_once __DIR__ . '/../config/db.php';

$jsonFile = __DIR__ . '/../../user_vocab.json';

if (!file_exists($jsonFile)) {
    die("Error: user_vocab.json not found in root directory.");
}

$jsonData = file_get_contents($jsonFile);
$words = json_decode($jsonData, true);

if (!$words) {
    die("Error: Invalid JSON format.");
}

try {
    // 1. Wipe existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE vocab_words");
    $pdo->exec("TRUNCATE TABLE user_vocab_progress");
    $pdo->exec("TRUNCATE TABLE user_vocab_stats"); // Reset stats too
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Tables wiped successfully.\n";

    // 2. Ensure default category exists
    $pdo->exec("INSERT IGNORE INTO vocab_categories (category_id, category_name) VALUES (1, 'General')");

    // 3. Prepare insert statement
    $sql = "INSERT INTO vocab_words (
                word, definition, definition_marathi, example_sentence, 
                set_number, level_name, word_type, difficulty_level, 
                synonyms, antonyms, category_id, options, correct_answer
            ) VALUES (
                :word, :definition, :def_marathi, :example, 
                :set_num, :level, :type, :diff, 
                :syn, :ant, 1, :opts, :ans
            )";
    
    $stmt = $pdo->prepare($sql);
    
    $count = 0;
    
    // Check if wrapped in "questions" key (User's custom format)
    if (isset($words['questions']) && is_array($words['questions'])) {
        $words = $words['questions'];
    }

    foreach ($words as $index => $item) {
        // Calculate Set Number (10 words per set)
        // Index 0-9 -> Set 1
        // Index 10-19 -> Set 2
        $setNum = floor($index / 10) + 1;
        
        $level = 'Beginner'; // Default
        if ($setNum > 5) $level = 'Intermediate';
        if ($setNum > 10) $level = 'Advanced';

        // Extract definitions intelligently
        $definition_english = '';
        $definition_marathi = '';

        // Case 1: explanation object (User format)
        if (isset($item['explanation']) && is_array($item['explanation'])) {
            $definition_english = $item['explanation']['english'] ?? '';
            $definition_marathi = $item['explanation']['marathi'] ?? '';
        } 
        // Case 2: Direct keys (Old format)
        else {
            $definition_english = $item['explanation_english'] ?? $item['definition'] ?? '';
            $definition_marathi = $item['explanation_marathi'] ?? $item['definition_marathi'] ?? '';
        }

        // Check if options exist
        $options = isset($item['options']) ? json_encode($item['options'], JSON_UNESCAPED_UNICODE) : null;
        $correctAnswer = $item['answer'] ?? null;

        $stmt->execute([
            ':word' => $item['word'],
            ':definition' => $definition_english,
            ':def_marathi' => $definition_marathi,
            ':example' => $item['question'] ?? $item['example_sentence'] ?? '',
            ':set_num' => $setNum,
            ':level' => $level,
            ':type' => 'noun',
            ':diff' => 1,
            ':syn' => '',
            ':ant' => '',
            ':opts' => $options,
            ':ans' => $correctAnswer
        ]);
        
        $count++;
    }
    
    echo "Successfully imported $count words into Sets 1-" . ceil($count/10) . ".\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
