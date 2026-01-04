<?php
/**
 * Append Vocab from JSON
 * Adds new words without wiping existing ones.
 * Automatically finds the next available Set Number.
 */

require_once __DIR__ . '/../config/db.php';

$jsonFile = __DIR__ . '/../../user_vocab_7.json';

if (!file_exists($jsonFile)) {
    die("Error: user_vocab_7.json not found in root directory.");
}

$jsonData = file_get_contents($jsonFile);
$words = json_decode($jsonData, true);

if (!$words) {
    die("Error: Invalid JSON format.");
}

try {
    // 1. Find the highest set number currently in the DB
    $stmt = $pdo->query("SELECT MAX(set_number) as max_set FROM vocab_words");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastSetNum = ($result['max_set'] > 0) ? intval($result['max_set']) : 0;
    
    // Check occupancy of last set
    $currentSetCount = 0;
    if ($lastSetNum > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vocab_words WHERE set_number = ?");
        $stmt->execute([$lastSetNum]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentSetCount = $row['count'];
    }

    $currentSet = ($lastSetNum > 0) ? $lastSetNum : 1;
    if ($currentSetCount >= 10) {
        $currentSet++; 
        $currentSetCount = 0;
    }

    echo "Starting smart append at Set $currentSet (Partially filled: $currentSetCount)\n";

    // 2. Ensure default category exists
    $pdo->exec("INSERT IGNORE INTO vocab_categories (category_id, category_name) VALUES (1, 'General')");

    // Pre-check statement
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM vocab_words WHERE word = ?");

    // 3. Prepare insert statement
    $sql = "INSERT INTO vocab_words (
                word, definition, definition_marathi, example_sentence, 
                set_number, level_name, word_type, difficulty_level, 
                synonyms, antonyms, category_id
            ) VALUES (
                :word, :definition, :def_marathi, :example, 
                :set_num, :level, :type, :diff, 
                :syn, :ant, 1
            )";
    
    $stmt = $pdo->prepare($sql);
    
    $count = 0;
    $skipped = 0;
    
    foreach ($words as $item) {
        
        // CHECK DUPLICATE
        $checkStmt->execute([$item['word']]);
        if ($checkStmt->fetchColumn() > 0) {
            $skipped++;
            continue; // Skip existing word
        }

        $level = 'Beginner';
        if ($currentSet > 5) $level = 'Intermediate';
        if ($currentSet > 10) $level = 'Advanced';

        $stmt->execute([
            ':word' => $item['word'],
            ':definition' => $item['explanation_english'] ?? $item['definition'],
            ':def_marathi' => $item['explanation_marathi'] ?? $item['definition_marathi'],
            ':example' => $item['question'] ?? $item['example_sentence'] ?? '',
            ':set_num' => $currentSet,
            ':level' => $level,
            ':type' => 'noun',
            ':diff' => 1,
            ':syn' => '',
            ':ant' => ''
        ]);
        
        $count++;
        $currentSetCount++;
        
        // Move to next set every 10 words
        if ($currentSetCount >= 10) {
            $currentSet++;
            $currentSetCount = 0;
        }
    }
    
    echo "Successfully appended $count NEW words. Skipped $skipped duplicates.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
