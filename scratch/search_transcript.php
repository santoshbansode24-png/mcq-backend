<?php
$file = 'C:\Users\ADMIN\.gemini\antigravity\brain\bbe2b035-ae9b-4ff7-9393-3e7e97ce3fd5\.system_generated\logs\transcript.jsonl';
$handle = fopen($file, 'r');
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'DgC4TupdfZ') !== false || strpos($line, 'E0:38:83') !== false) {
            // Find text around matches
            echo "Match:\n";
            // decode json
            $decoded = json_decode($line, true);
            if (isset($decoded['content'])) {
                echo "Content: " . substr($decoded['content'], 0, 500) . "...\n";
            } else if (isset($decoded['tool_calls'])) {
                echo "Tool Calls: " . json_encode($decoded['tool_calls']) . "\n";
            } else {
                echo substr($line, 0, 500) . "...\n";
            }
        }
    }
    fclose($handle);
} else {
    echo "Could not open transcript.\n";
}
?>
