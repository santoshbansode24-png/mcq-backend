<?php
function findFiles($dir, $extensions) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                echo $file->getPathname() . " (Size: " . $file->getSize() . " bytes)\n";
            }
            if (strpos(strtolower($file->getFilename()), 'keystore') !== false) {
                echo $file->getPathname() . " (Size: " . $file->getSize() . " bytes) [contains 'keystore']\n";
            }
        }
    }
}

echo "Searching for keystore or jks files...\n";
findFiles('c:\xampp\htdocs\veeru', ['keystore', 'jks', 'pepk']);
?>
