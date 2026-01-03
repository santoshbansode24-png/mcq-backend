<?php
$json = file_get_contents(__DIR__ . '/full_models.json');
$data = json_decode($json, true);

if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        echo $model['name'] . "\n";
    }
} else {
    echo "Could not parse models list.\n";
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
    // Print raw first 500 chars to debug
    echo "Raw start: " . substr($json, 0, 500) . "\n";
}
?>
