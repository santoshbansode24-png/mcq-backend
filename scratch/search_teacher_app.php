<?php
function searchInDir($dir, $queries) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        if (strpos($filePath, 'node_modules') !== false || 
            strpos($filePath, '.git') !== false || 
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

searchInDir('c:/xampp/htdocs/veeru/teacher_app', ['portal', 'connecting to', 'connecting']);
?>
