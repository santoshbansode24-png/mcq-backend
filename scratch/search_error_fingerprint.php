<?php
$file = 'C:\Users\ADMIN\.gemini\antigravity\brain\bbe2b035-ae9b-4ff7-9393-3e7e97ce3fd5\.system_generated\logs\transcript.jsonl';
$handle = fopen($file, 'r');
$step = 0;
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $decoded = json_decode($line, true);
        $step = $decoded['step_index'] ?? $step;
        if ($step < 430) {
            $content = $decoded['content'] ?? '';
            if (strpos($content, 'fingerprint') !== false || strpos($content, 'expected') !== false || strpos($content, 'signing') !== false || strpos($content, 'D2:35') !== false) {
                echo "=== Step $step ===\n";
                echo $content . "\n\n";
            }
        } else {
            break;
        }
    }
    fclose($handle);
}
?>
