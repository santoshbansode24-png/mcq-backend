<?php
require_once 'backend/config/ai_config.php';
$key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : 'NOT_DEFINED';
if ($key === 'NOT_DEFINED') {
    echo "STATUS: NO KEY DEFINED. AI SHOULD NOT WORK.\n";
} else if (empty($key)) {
    echo "STATUS: KEY IS EMPTY STRING. AI SHOULD NOT WORK.\n";
} else {
    $masked = substr($key, 0, 8) . "..." . substr($key, -4);
    echo "STATUS: KEY FOUND! [$masked]\n";
}
