<?php
/**
 * Diagnostic Script: Check Vocab Data
 * Checks if vocabulary data exists in database and is accessible
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

echo "=== VOCAB DATA DIAGNOSTIC ===\n\n";

try {
    // 1. Check total words in database
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vocab_words");
    $totalWords = $stmt->fetch()['count'];
    echo "✓ Total words in database: $totalWords\n\n";
    
    if ($totalWords == 0) {
        echo "❌ ERROR: No vocabulary words found in database!\n";
        echo "   Please run vocab_seeder.php first to import data.\n";
        exit;
    }
    
    // 2. Check words per set
    echo "=== Words per Set ===\n";
    $stmt = $pdo->query("
        SELECT set_number, COUNT(*) as count 
        FROM vocab_words 
        GROUP BY set_number 
        ORDER BY set_number 
        LIMIT 20
    ");
    foreach ($stmt->fetchAll() as $row) {
        echo "Set {$row['set_number']}: {$row['count']} words\n";
    }
    echo "\n";
    
    // 3. Check sample words from Set 1
    echo "=== Sample Words from Set 1 ===\n";
    $stmt = $pdo->query("
        SELECT word_id, word, definition, options, correct_answer 
        FROM vocab_words 
        WHERE set_number = 1 
        LIMIT 3
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $word) {
        echo "Word ID: {$word['word_id']}\n";
        echo "Word: {$word['word']}\n";
        echo "Definition: {$word['definition']}\n";
        echo "Options: {$word['options']}\n";
        echo "Correct Answer: {$word['correct_answer']}\n";
        echo "---\n";
    }
    
    // 4. Test API endpoint simulation
    echo "\n=== API Endpoint Test (Set 1, User ID 1) ===\n";
    $userId = 1;
    $setNumber = 1;
    
    $stmt = $pdo->prepare("
        SELECT 
            vw.word_id,
            vw.word,
            vw.definition,
            vw.definition_marathi,
            vw.options,
            vw.correct_answer,
            vw.set_number
        FROM vocab_words vw
        WHERE vw.set_number = :set_number
        ORDER BY vw.word_id ASC
        LIMIT 25
    ");
    $stmt->execute([':set_number' => $setNumber]);
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Words returned: " . count($words) . "\n";
    if (count($words) > 0) {
        echo "✓ API query works correctly!\n";
        echo "First word: {$words[0]['word']}\n";
    } else {
        echo "❌ ERROR: No words returned from API query!\n";
    }
    
    // 5. Check if vocab_categories table has data
    echo "\n=== Vocab Categories ===\n";
    $stmt = $pdo->query("SELECT category_id, category_name FROM vocab_categories LIMIT 10");
    $categories = $stmt->fetchAll();
    echo "Total categories: " . count($categories) . "\n";
    foreach ($categories as $cat) {
        echo "- {$cat['category_name']} (ID: {$cat['category_id']})\n";
    }
    
    echo "\n✅ DIAGNOSTIC COMPLETE\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
