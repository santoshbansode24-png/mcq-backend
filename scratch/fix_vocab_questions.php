<?php
// scratch/fix_vocab_questions.php
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

// Dictionary mapping for target words to clean synonyms/meanings
$synonymMap = [
    // user_vocab_4.json
    "protect" => "defend",
    "proud" => "pleased",
    "prove" => "verify",
    "provide" => "supply",
    "public" => "open",
    "publish" => "issue",
    "punish" => "penalize",
    "purchase" => "buy",
    "pure" => "clean",
    "purpose" => "goal",
    "pursue" => "chase",
    "qualify" => "pass",
    "quality" => "standard",
    "quantity" => "amount",
    "quarrel" => "fight",
    "question" => "query",
    "quick" => "fast",
    "quiet" => "silent",
    "quit" => "stop",
    "raise" => "lift",
    "rare" => "uncommon",
    "reach" => "arrive",
    "react" => "respond",
    "realize" => "understand",
    "reason" => "cause",
    "receive" => "get",
    "recent" => "new",
    "recognize" => "identify",
    "recommend" => "suggest",
    "recover" => "heal",
    "reduce" => "lower",
    "refuse" => "decline",
    "regret" => "repent",
    "regular" => "usual",
    "reject" => "decline",
    "relax" => "rest",
    "release" => "free",
    "remain" => "stay",
    "remember" => "recall",
    "remind" => "prompt",
    "remove" => "delete",
    "repair" => "fix",
    "repeat" => "restate",
    "replace" => "substitute",
    "reply" => "answer",
    "report" => "state",
    "request" => "ask",
    "require" => "need",
    "rescue" => "save",
    "respect" => "honor",

    // user_vocab_5.json
    "accomplish" => "achieve",
    "adapt" => "adjust",
    "adequate" => "sufficient",
    "admire" => "praise",
    "admit" => "confess",
    "adopt" => "embrace",
    "advantage" => "benefit",
    "advertise" => "promote",
    "affect" => "influence",
    "afford" => "pay for",
    "agency" => "organization",
    "agent" => "representative",
    "agree" => "consent",
    "ahead" => "forward",
    "allow" => "permit",
    "almost" => "nearly",
    "already" => "previously",
    "although" => "even though",
    "always" => "forever",
    "amazing" => "wonderful",
    "amount" => "quantity",
    "ancient" => "very old",
    "anger" => "rage",
    "announce" => "declare",
    "annoy" => "irritate",
    "anxious" => "worried",
    "appear" => "seem",
    "apply" => "request",
    "approach" => "come near",
    "approve" => "accept",
    "arrange" => "organize",
    "arrive" => "reach",
    "ashamed" => "embarrassed",
    "assist" => "help",
    "assume" => "suppose",
    "astonish" => "surprise",
    "attach" => "connect",
    "attack" => "assault",
    "attempt" => "try",
    "attend" => "present",
    "attract" => "draw",
    "avoid" => "evade",
    "aware" => "conscious",
    "awful" => "terrible",
    "awkward" => "clumsy",
    "bargain" => "deal",
    "barrier" => "obstacle",
    "basic" => "fundamental",
    "battle" => "fight",
    "beauty" => "attractiveness",

    // user_vocab_6.json
    "belief" => "faith",
    "belong" => "pertain",
    "beneath" => "under",
    "benefit" => "advantage",
    "beyond" => "further",
    "bill" => "invoice",
    "bitter" => "sharp-tasting",
    "blame" => "accuse",
    "blank" => "empty",
    "blind" => "sightless",
    "block" => "obstruct",
    "blood" => "red fluid",
    "boil" => "heat to bubble",
    "bold" => "daring",
    "bond" => "connection",
    "border" => "boundary",
    "bother" => "trouble",
    "brain" => "mind",
    "branch" => "division",
    "brand" => "trademark",
    "brave" => "courageous",
    "breath" => "inhalation",
    "brief" => "short",
    "brilliant" => "bright",
    "broad" => "wide",
    "budget" => "financial plan",
    "burst" => "explode",
    "calculate" => "compute",
    "calm" => "peaceful",
    "campaign" => "drive",
    "cancel" => "call off",
    "candidate" => "applicant",
    "capacity" => "volume",
    "capture" => "catch",
    "careless" => "unheedful",
    "category" => "group",
    "cease" => "stop",
    "celebration" => "festivity",
    "cell" => "small unit",
    "ceremony" => "ritual",
    "certain" => "sure",
    "chain" => "series",
    "chairman" => "leader",
    "challenge" => "task",
    "chamber" => "room",
    "character" => "nature",
    "charge" => "demand",
    "charity" => "donation",
    "chart" => "graph",

    // user_vocab_7.json
    "chase" => "pursue",
    "cheat" => "deceive",
    "cheer" => "applause",
    "chemical" => "substance",
    "chest" => "torso",
    "chief" => "head",
    "childhood" => "early years",
    "choice" => "option",
    "choose" => "select",
    "church" => "chapel",
    "circle" => "ring",
    "circumstance" => "situation",
    "citizen" => "resident",
    "claim" => "assert",
    "clarify" => "explain",
    "classic" => "timeless",
    "clear" => "plain",
    "clerk" => "office worker",
    "clever" => "smart",
    "client" => "customer",
    "climate" => "weather",
    "climb" => "ascend",
    "close" => "near",
    "cloth" => "fabric",
    "clue" => "hint",
    "coach" => "trainer",
    "coal" => "black fuel",
    "coast" => "shore",
    "return" => "come back",
    "reveal" => "disclose",
    "revenge" => "retaliation"
];

$jsonPattern = __DIR__ . '/../admin/user_vocab*.json';
$files = glob($jsonPattern);

echo "📂 Processing JSON files to fix self-repeating options...\n";

foreach ($files as $file) {
    $fileName = basename($file);
    $data = file_get_contents($file);
    $jsonObj = json_decode($data, true);
    
    $isWrapped = isset($jsonObj['questions']) && is_array($jsonObj['questions']);
    $words = $isWrapped ? $jsonObj['questions'] : $jsonObj;

    $updatedCount = 0;

    foreach ($words as &$item) {
        $word = trim($item['word'] ?? '');
        $wordLower = strtolower($word);

        // Ensure definition fields are set
        if (empty($item['definition']) && !empty($item['explanation_english'])) {
            $item['definition'] = $item['explanation_english'];
        }
        if (empty($item['definition_marathi']) && !empty($item['explanation_marathi'])) {
            $item['definition_marathi'] = $item['explanation_marathi'];
        }

        // Check options
        $options = $item['options'] ?? [];
        $isAssoc = !isset($options[0]);

        $newSynonym = $synonymMap[$wordLower] ?? null;

        // Fallback for synonym if not in map
        if (!$newSynonym && !empty($item['explanation_english'])) {
            $parts = explode(';', $item['explanation_english']);
            $firstPart = trim($parts[0]);
            if (strlen($firstPart) > 0 && strlen($firstPart) < 30) {
                $newSynonym = ucfirst($firstPart);
            }
        }
        if (!$newSynonym) {
            $newSynonym = "synonym of " . $word;
        }

        $replaced = false;
        foreach ($options as $k => $val) {
            if (strtolower(trim($val)) === $wordLower) {
                $options[$k] = ucfirst($newSynonym);
                $replaced = true;
            }
        }

        if ($replaced) {
            $item['options'] = $options;
            // Update correct answer
            if (strtolower(trim($item['correct_answer'] ?? '')) === $wordLower) {
                $item['correct_answer'] = ucfirst($newSynonym);
            }
            $updatedCount++;
        }
    }

    if ($isWrapped) {
        $jsonObj['questions'] = $words;
    } else {
        $jsonObj = $words;
    }

    file_put_contents($file, json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "   - $fileName: Fixed $updatedCount self-repeating word options.\n";
}

echo "\n✅ All JSON files cleaned successfully!\n";
?>
