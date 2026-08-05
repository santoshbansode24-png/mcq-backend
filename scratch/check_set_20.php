<?php
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Fetch all words in set_number = 20
    $stmt = $pdo->prepare("SELECT word_id, word, definition, definition_marathi, options, correct_answer, set_number FROM vocab_words WHERE set_number = 20 ORDER BY word_id ASC");
    $stmt->execute();
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total words in Set 20: " . count($words) . "\n\n";

    $seenWords = [];
    $duplicates = [];

    foreach ($words as $w) {
        $wordClean = strtolower(trim($w['word']));
        echo "ID: {$w['word_id']} | Word: {$w['word']} | Def: {$w['definition']} | Marathi: {$w['definition_marathi']} | Ans: {$w['correct_answer']}\n";
        echo "   Options: {$w['options']}\n";

        if (isset($seenWords[$wordClean])) {
            $duplicates[] = [
                'original' => $seenWords[$wordClean],
                'duplicate' => $w
            ];
        } else {
            $seenWords[$wordClean] = $w;
        }
    }

    echo "\n-----------------------------------------\n";
    if (!empty($duplicates)) {
        echo "⚠️ DUPLICATE/REPEATED WORDS FOUND IN SET 20:\n";
        foreach ($duplicates as $d) {
            echo "Original ID {$d['original']['word_id']} ('{$d['original']['word']}') vs Duplicate ID {$d['duplicate']['word_id']} ('{$d['duplicate']['word']}')\n";
        }
    } else {
        echo "No exact duplicate word names found in Set 20 database table.\n";
    }

    // Also check for all sets if there are duplicate words in ANY set
    echo "\n-----------------------------------------\n";
    echo "CHECKING ALL SETS FOR DUPLICATES IN SET 20 VS OTHER SETS:\n";
    $allStmt = $pdo->query("SELECT word_id, word, set_number FROM vocab_words ORDER BY set_number, word_id");
    $allWords = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $wordToSets = [];
    foreach ($allWords as $aw) {
        $wClean = strtolower(trim($aw['word']));
        if (!isset($wordToSets[$wClean])) {
            $wordToSets[$wClean] = [];
        }
        $wordToSets[$wClean][] = [
            'id' => $aw['word_id'],
            'set' => $aw['set_number']
        ];
    }

    foreach ($wordToSets as $w => $occurrences) {
        if (count($occurrences) > 1) {
            $hasSet20 = false;
            foreach ($occurrences as $oc) {
                if ($oc['set'] == 20) $hasSet20 = true;
            }
            if ($hasSet20) {
                echo "Word '$w' appears multiple times across sets: ";
                foreach ($occurrences as $oc) {
                    echo "[ID: {$oc['id']}, Set: {$oc['set']}] ";
                }
                echo "\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>
