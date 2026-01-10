<?php
/**
 * Vocabulary Database Seeder & Merger
 * 1. Automatically finds all user_vocab*.json files in this folder.
 * 2. Merges them into one master list.
 * 3. Sorts by difficulty.
 * 4. Inserts into Database with auto-set numbering.
 */

// Increase limits for large data
set_time_limit(600); 
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

function trimmed_value($val) {
    return is_string($val) ? trim($val) : $val;
}

// --- CONFIGURATION ---
// 1. Load All user_vocab*.json files
$jsonPattern = __DIR__ . '/user_vocab*.json';
$files = glob($jsonPattern);
$allWords = [];

if (empty($files)) {
    die("❌ Error: I cannot find any files named 'user_vocab.json', 'user_vocab_2.json', etc. in this folder.\n");
}

echo "📂 Found " . count($files) . " JSON files. Merging...\n";

foreach ($files as $file) {
    $data = file_get_contents($file);
    $jsonObj = json_decode($data, true);
    $words = [];

    if (isset($jsonObj['questions']) && is_array($jsonObj['questions'])) {
        $words = $jsonObj['questions'];
    } elseif (is_array($jsonObj)) {
        $words = $jsonObj;
    }
    
    if (is_array($words)) {
        $count = count($words);
        $fileName = basename($file);
        $allWords = array_merge($allWords, $words);
        echo "   - Loaded $count words from $fileName\n";
    } else {
        echo "   ⚠️ Warning: Could not read words from " . basename($file) . " (Invalid JSON)\n";
    }
}

$totalWords = count($allWords);
echo "✅ Total Words Loaded (Pre-dedupe): $totalWords\n\n";

// 1.5 Deduplicate Words (Keep first occurrence)
// Use an associative array keyed by 'word' (lowercase) to filter duplicates
$uniqueWords = [];
foreach ($allWords as $item) {
    if (isset($item['word'])) {
        $key = strtolower(trim($item['word']));
        if (!isset($uniqueWords[$key])) {
            $uniqueWords[$key] = $item;
        }
    }
}
$allWords = array_values($uniqueWords);
$dedupedCount = count($allWords);
echo "✅ Unique Words Count: $dedupedCount (Removed " . ($totalWords - $dedupedCount) . " duplicates)\n\n";

// 2. Sort Words (Easy -> Medium -> Hard)
echo "🔄 Sorting words by difficulty...\n";

$difficultyWeight = [
    'Easy' => 1, 'Beginner' => 1,
    'Medium' => 2, 'Intermediate' => 2,
    'Hard' => 3, 'Advanced' => 3
];

usort($allWords, function ($a, $b) use ($difficultyWeight) {
    $diffA = $a['difficulty_level'] ?? 'Medium';
    $diffB = $b['difficulty_level'] ?? 'Medium';
    return ($difficultyWeight[$diffA] ?? 2) <=> ($difficultyWeight[$diffB] ?? 2);
});

// 3. Database Insertion
echo "🚀 Starting Database Wipe & Insertion...\n";

try {
    // WIPE OLD DATA (DDL implicit commit, so do outside transaction)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE vocab_words");
    $pdo->exec("TRUNCATE TABLE user_vocab_progress");
    $pdo->exec("TRUNCATE TABLE user_vocab_stats"); 
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "🧹 Wiped existing vocabulary tables.\n";

    // FORCE TABLE AND COLUMNS TO UTF8MB4 (Implicit Commit, so do outside transaction)
    $pdo->exec("ALTER TABLE vocab_words CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "🔧 Converted table to UTF8MB4.\n";

    $pdo->beginTransaction();
    $pdo->exec("SET NAMES utf8mb4"); // FORCE UTF-8

    // DEBUG: Check Connection Charset
    // $res = $pdo->query("SHOW VARIABLES LIKE 'character_set_client'")->fetch(PDO::FETCH_ASSOC);
    // echo "🧐 DEBUG Client Charset: " . ($res['Value'] ?? 'N/A') . "\n";

    // Prepare Categories
    $catStmt = $pdo->prepare("INSERT IGNORE INTO vocab_categories (category_name, access_level) VALUES (:name, 'Free')");
    $getCatId = $pdo->prepare("SELECT category_id FROM vocab_categories WHERE category_name = :name");


    // Prepare Word Insert
    $wordStmt = $pdo->prepare("
        INSERT INTO vocab_words 
        (word, definition, definition_marathi, example_sentence, set_number, difficulty_level, category_id, options, correct_answer) 
        VALUES 
        (:word, :def, :def_mar, :ex, :set, :diff, :cat_id, :opts, :correct)
    ");

    $count = 0;
    
    foreach ($allWords as $index => $item) {
        // Calculate Set Number (0-9 = Set 1, 10-19 = Set 2) - 10 WORDS PER SET
        $currentSet = floor($index / 10) + 1;

        // Handle Category
        $catName = $item['category_name'] ?? 'General';
        $catStmt->execute([':name' => $catName]);
        $getCatId->execute([':name' => $catName]);
        $categoryId = $getCatId->fetchColumn();

        // Encode Options safely (Fix for Set 15/Old Format)
        $options = $item['options'] ?? [];
        
        // If options is a simple array (0,1,2,3), convert to A,B,C,D
        if (isset($options[0]) && !isset($options['A'])) {
            $mappedOptions = [];
            $keys = ['A', 'B', 'C', 'D'];
            foreach ($options as $k => $val) {
                if (isset($keys[$k])) {
                    $mappedOptions[$keys[$k]] = $val;
                }
            }
            $options = $mappedOptions;
        }

        $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);

        // Handle Correct Answer Mapping
        // If correct_answer is "Protect" (text), find which key (A,B,C,D) holds it.
        $correctRaw = trimmed_value($item['correct_answer'] ?? 'A');
        $correctKey = $correctRaw; // Default to raw value (e.g. "A" or "B")

        // If the raw value is NOT a valid key (A,B,C,D), try to find it in values
        if (!isset($options[$correctRaw])) {
            $foundKey = array_search($correctRaw, $options);
            if ($foundKey !== false) {
                $correctKey = $foundKey;
            } else {
                // Fallback: Check case-insensitive
                foreach ($options as $key => $val) {
                    if (strcasecmp(trim($val), trim($correctRaw)) === 0) {
                        $correctKey = $key;
                        break;
                    }
                }
            }
        }

        // Insert
        $wordStmt->execute([
            ':word'     => $item['word'],
            ':def'      => $item['definition'] ?? $item['explanation']['english'] ?? 'No definition',
            ':def_mar'  => $item['definition_marathi'] ?? $item['explanation']['marathi'] ?? '',
            ':ex'       => $item['example_sentence'] ?? '',
            ':set'      => $currentSet,
            ':diff'     => $item['difficulty_level'] ?? 'Medium',
            ':cat_id'   => $categoryId,
            ':opts'     => $optionsJson,
            ':correct'  => $correctKey
        ]);

        $count++;
    }

    $pdo->commit();
    
    echo "\n🎉 SUCCESS! Inserted $count words across $currentSet sets.\n";
    echo "   - Sets 1 to " . floor($currentSet/3) . " are Easy\n";
    echo "   - Middle sets are Medium\n";
    echo "   - Last sets are Hard\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ FAILED: " . $e->getMessage();
}
?>