<?php
$jsonPattern = __DIR__ . '/../admin/user_vocab*.json';
$files = glob($jsonPattern);
$allWords = [];

echo "📂 Loading JSON files...\n";
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
        $fileName = basename($file);
        echo "   - $fileName: " . count($words) . " words\n";
        $allWords = array_merge($allWords, $words);
    }
}

echo "Total raw words: " . count($allWords) . "\n";

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
echo "Unique words count: " . count($allWords) . "\n\n";

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

// Group by sets (10 words per set)
$sets = [];
foreach ($allWords as $index => $item) {
    $setNum = floor($index / 10) + 1;
    if (!isset($sets[$setNum])) {
        $sets[$setNum] = [];
    }
    $sets[$setNum][] = [
        'index' => $index,
        'word' => $item['word'] ?? '',
        'def' => $item['definition'] ?? '',
        'def_mar' => $item['definition_marathi'] ?? '',
        'options' => $item['options'] ?? [],
        'correct' => $item['correct_answer'] ?? ''
    ];
}

echo "=========================================\n";
echo "WORDS IN SET 20 (Index 190-199):\n";
echo "=========================================\n";
if (isset($sets[20])) {
    foreach ($sets[20] as $w) {
        echo "[Index #{$w['index']}] Word: {$w['word']} | Def: {$w['def']} | Marathi: {$w['def_mar']}\n";
        echo "   Options: " . json_encode($w['options']) . " | Ans: {$w['correct']}\n";
    }
} else {
    echo "Set 20 does not exist in merged JSON data! (Total sets: " . count($sets) . ")\n";
}

echo "\n=========================================\n";
echo "CHECKING ALL SETS FOR DUPLICATES / REPEATED QUESTIONS:\n";
echo "=========================================\n";

$seenWordsAcrossSets = [];
foreach ($sets as $setNum => $wordsInSet) {
    $wordsInThisSet = [];
    foreach ($wordsInSet as $w) {
        $wClean = strtolower(trim($w['word']));
        if (isset($wordsInThisSet[$wClean])) {
            echo "⚠️ REPEATED WORD WITHIN SET $setNum: '{$w['word']}'\n";
        }
        $wordsInThisSet[$wClean] = true;

        if (isset($seenWordsAcrossSets[$wClean])) {
            echo "⚠️ REPEATED WORD ACROSS SETS: '{$w['word']}' in Set $setNum (previously seen in Set {$seenWordsAcrossSets[$wClean]})\n";
        } else {
            $seenWordsAcrossSets[$wClean] = $setNum;
        }
    }
}
?>
