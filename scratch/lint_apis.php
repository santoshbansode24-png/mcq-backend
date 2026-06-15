<?php
/**
 * Quick Lint Audit for Backend APIs
 */

$files = [
    'backend/api/google_login.php',
    'backend/api/login.php',
    'backend/api/register.php',
    'backend/api/get_subjects.php',
    'backend/api/get_chapters.php',
    'backend/api/get_mcqs.php',
    'backend/api/cors_middleware.php',
    'backend/config/db.php'
];

echo "--- RUNNING SYNTAX LINT CHECK ---\n";
foreach ($files as $file) {
    if (file_exists($file)) {
        $output = [];
        $return_var = 0;
        // Run php -l command
        exec("c:\\xampp\\php\\php.exe -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "✅ $file: Syntax OK\n";
        } else {
            echo "❌ $file: SYNTAX ERROR!\n";
            echo implode("\n", $output) . "\n\n";
        }
    } else {
        echo "⚠️ $file: File does not exist!\n";
    }
}
?>
