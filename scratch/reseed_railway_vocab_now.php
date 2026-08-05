<?php
// scratch/reseed_railway_vocab_now.php
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

echo "🚀 Reseeding Railway Production Database ($host:$port)... \n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 60
    ]);
    echo "✅ Connected to Railway Production Database!\n";

    // Load All user_vocab*.json files from admin/
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

    // Deduplicate words
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

    // Disable foreign keys and clear tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE vocab_words");
    $pdo->exec("TRUNCATE TABLE user_vocab_progress");
    $pdo->exec("TRUNCATE TABLE user_vocab_stats");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "🧹 Cleared old vocabulary tables.\n";

    // Ensure category exists
    $pdo->exec("INSERT IGNORE INTO vocab_categories (category_id, category_name, access_level) VALUES (1, 'General', 'Free')");

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

        if (!isset($options[$correctRaw])) {
            foreach ($options as $k => $v) {
                if (strcasecmp(trim($v), $correctRaw) === 0) {
                    $correctKey = $k;
                    break;
                }
            }
        }

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
    }

    echo "🎉 Successfully inserted $insertedCount cleaned vocabulary words into Railway Production Database!\n\n";

    $setCountStmt = $pdo->query("SELECT set_number, COUNT(*) as count FROM vocab_words GROUP BY set_number ORDER BY set_number ASC");
    $setRows = $setCountStmt->fetchAll();
    echo "VERIFYING SETS IN RAILWAY DATABASE:\n";
    foreach ($setRows as $sr) {
        echo "Set {$sr['set_number']}: {$sr['count']} words\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
