<?php
function searchInDir($dir, $queries) {
    if (!is_dir($dir)) return;
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

$dirs = [
    'c:/xampp/htdocs/educational mcq project',
    'c:/xampp/htdocs/mcq project1.0',
    'c:/xampp/htdocs/eduapp'
];

foreach ($dirs as $dir) {
    echo "\nSearching in $dir...\n";
    searchInDir($dir, ['portal', 'connecting to', 'connecting']);
}
?>
