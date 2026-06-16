<?php
function searchInDir($dir, $queries) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        // Skip node_modules, .git, and android build directories
        if (strpos($filePath, 'node_modules') !== false || 
            strpos($filePath, '.git') !== false || 
            strpos($filePath, 'android') !== false ||
            strpos($filePath, '.expo') !== false) {
            continue;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) continue;

        foreach ($queries as $query) {
            if (stripos($content, $query) !== false) {
                echo "Found '$query' in: $filePath\n";
                // Print lines
                $lines = explode("\n", $content);
                foreach ($lines as $num => $line) {
                    if (stripos($line, $query) !== false) {
                        echo "  Line " . ($num + 1) . ": " . trim($line) . "\n";
                    }
                }
            }
        }
    }
}

echo "Searching in student_app...\n";
searchInDir('c:/xampp/htdocs/veeru/student_app', ['portal', 'connecting to', 'connecting']);

echo "\nSearching in api and backend...\n";
searchInDir('c:/xampp/htdocs/veeru/api', ['portal', 'connecting to', 'connecting']);
searchInDir('c:/xampp/htdocs/veeru/backend', ['portal', 'connecting to', 'connecting']);
?>
