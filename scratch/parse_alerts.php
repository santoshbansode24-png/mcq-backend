<?php
function parseAlerts($dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        if (strpos($filePath, 'node_modules') !== false || 
            strpos($filePath, '.git') !== false || 
            strpos($filePath, 'android') !== false ||
            strpos($filePath, '.expo') !== false) {
            continue;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) continue;

        // Simple regex to find Alert.alert(...) calls
        // Matches across multiple lines
        preg_match_all('/Alert\.alert\s*\(\s*([^)]+)\)/s', $content, $matches);
        if (!empty($matches[0])) {
            echo "File: $filePath\n";
            foreach ($matches[0] as $match) {
                echo "  " . trim(preg_replace('/\s+/', ' ', $match)) . "\n";
            }
        }
    }
}

parseAlerts('c:/xampp/htdocs/veeru/student_app');
?>
