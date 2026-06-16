<?php
function searchInDir($dir, $queries) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        // Skip build directories
        if (strpos($filePath, '/build/') !== false || strpos($filePath, '\\build\\') !== false) {
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

searchInDir('c:/xampp/htdocs/veeru/student_app/android', ['portal', 'connecting to', 'connecting']);
?>
