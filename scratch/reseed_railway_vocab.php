<?php
// scratch/reseed_railway_vocab.php
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

echo "🚀 Connecting to Railway Production Database ($host:$port)... \n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Connected to Railway Production Database successfully!\n\n";

    // 1. Load All user_vocab*.json files from admin/
    $jsonPattern = __DIR__ . '/../admin/user_vocab*.json';
    $files = glob($jsonPattern);
    $allWords = [];

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
            $allWords = array_merge($allWords, $words);
        }
    }

    // Deduplicate words keeping first occurrence
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

    // Sort Easy -> Medium -> Hard
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

    echo "📊 Total Clean Unique Vocabulary Words to Seed: " . count($allWords) . "\n";

    // Delete existing records
    echo "🧹 Clearing old vocab_words table...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM vocab_words");
    $pdo->exec("ALTER TABLE vocab_words AUTO_INCREMENT = 1");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Cleared vocab_words table.\n";

    // Ensure category exists
    $pdo->exec("INSERT IGNORE INTO vocab_categories (category_id, category_name, access_level) VALUES (1, 'General', 'Free')");

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO vocab_words 
        (word, definition, definition_marathi, example_sentence, set_number, difficulty_level, category_id, options, correct_answer) 
        VALUES 
        (:word, :def, :def_mar, :ex, :set_num, :diff, 1, :opts, :correct)
    ");

    $insertedCount = 0;

    foreach ($allWords as $index => $item) {
        $setNum = floor($index / 10) + 1;
        $wordStr = trim($item['word'] ?? '');
        $defStr = trim($item['definition'] ?? $item['explanation_english'] ?? '');
        $defMarStr = trim($item['definition_marathi'] ?? $item['explanation_marathi'] ?? '');
        $exStr = trim($item['example_sentence'] ?? '');
        $diffStr = ucfirst(trim($item['difficulty_level'] ?? 'Medium'));

        $options = $item['options'] ?? [];
        if (isset($options[0]) && !isset($options['A'])) {
            $mapped = [];
            $keys = ['A', 'B', 'C', 'D'];
            foreach ($options as $k => $v) {
                if (isset($keys[$k])) $mapped[$keys[$k]] = $v;
            }
            $options = $mapped;
        }

        $optsJson = json_encode($options, JSON_UNESCAPED_UNICODE);
        
        $correctRaw = trim($item['correct_answer'] ?? 'A');
        $correctKey = $correctRaw;

        // If raw correct answer is text (e.g. "Achieve"), map to key A, B, C, D
        if (!isset($options[$correctRaw])) {
            foreach ($options as $k => $v) {
                if (strcasecmp(trim($v), $correctRaw) === 0) {
                    $correctKey = $k;
                    break;
                }
            }
        }

        try {
            $stmt->execute([
                ':word' => $wordStr,
                ':def' => $defStr,
                ':def_mar' => $defMarStr,
                ':ex' => $exStr,
                ':set_num' => $setNum,
                ':diff' => $diffStr,
                ':opts' => $optsJson,
                ':correct' => $correctKey
            ]);
            $insertedCount++;
        } catch (Exception $insertEx) {
            echo "❌ Insert error at index $index ('$wordStr'): " . $insertEx->getMessage() . "\n";
            throw $insertEx;
        }
    }

    $pdo->commit();

    echo "🎉 Successfully inserted $insertedCount cleaned vocabulary words into Railway Production Database!\n";

    // 3. Verify Set 20 in live database
    $verifyStmt = $pdo->prepare("SELECT word_id, word, definition, definition_marathi, options, correct_answer FROM vocab_words WHERE set_number = 20 ORDER BY word_id ASC");
    $verifyStmt->execute();
    $set20Live = $verifyStmt->fetchAll();

    echo "\n=========================================\n";
    echo "VERIFYING LIVE SET 20 IN RAILWAY DATABASE:\n";
    echo "=========================================\n";
    foreach ($set20Live as $w) {
        echo "ID {$w['word_id']} | Word: {$w['word']} | Def: {$w['definition']} | Ans Key: {$w['correct_answer']} | Opts: {$w['options']}\n";
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
