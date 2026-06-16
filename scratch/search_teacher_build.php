<?php
function searchInDir($dir, $queries) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        
        $content = @file_get_contents($filePath);
        if ($content === false) continue;

        foreach ($queries as $query) {
            if (stripos($content, $query) !== false) {
                echo "Found '$query' in: $filePath\n";
            }
        }
    }
}

searchInDir('c:/xampp/htdocs/veeru/teacher', ['portal', 'connecting to', 'connecting']);
?>
