<?php
$file = 'C:\Users\ADMIN\.gemini\antigravity\brain\bbe2b035-ae9b-4ff7-9393-3e7e97ce3fd5\.system_generated\logs\transcript.jsonl';
$lines = file($file);
$start = 468;
$end = 490;
for ($i = $start; $i < $end; $i++) {
    if (!isset($lines[$i])) continue;
    $decoded = json_decode($lines[$i], true);
    echo "=== STEP $i (Type: " . ($decoded['type'] ?? 'unknown') . ", Source: " . ($decoded['source'] ?? 'unknown') . ") ===\n";
    if (isset($decoded['content'])) {
        echo substr($decoded['content'], 0, 800) . "\n\n";
    }
}
?>
