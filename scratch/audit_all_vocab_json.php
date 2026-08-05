<?php
$jsonPattern = __DIR__ . '/../admin/user_vocab*.json';
$files = glob($jsonPattern);

foreach ($files as $file) {
    $fileName = basename($file);

    $data = file_get_contents($file);
    $jsonObj = json_decode($data, true);
    $words = [];
    if (isset($jsonObj['questions']) && is_array($jsonObj['questions'])) {
        $words = $jsonObj['questions'];
    } elseif (is_array($jsonObj)) {
        $words = $jsonObj;
    }

    $repeatedWordCount = 0;
    $missingDefCount = 0;

    foreach ($words as $idx => $item) {
        $word = trim($item['word'] ?? '');
        $wordLower = strtolower($word);
        
        $def = $item['definition'] ?? $item['explanation_english'] ?? '';

        if (empty($def)) {
            $missingDefCount++;
        }

        $options = $item['options'] ?? [];
        $hasSelfOption = false;

        foreach ($options as $k => $optVal) {
            if (strtolower(trim($optVal)) === $wordLower) {
                $hasSelfOption = true;
                break;
            }
        }

        if ($hasSelfOption) {
            $repeatedWordCount++;
        }
    }

    echo "Summary for $fileName:\n";
    echo "  - Total Words: " . count($words) . "\n";
    echo "  - Self-Repeating Word Options: $repeatedWordCount\n";
    echo "  - Missing Definitions: $missingDefCount\n\n";
}
?>
