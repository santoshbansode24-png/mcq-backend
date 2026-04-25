<?php
$dir = __DIR__;
$files = glob($dir . '/*.php');
$version = time();
foreach ($files as $file) {
    if (basename($file) == 'bust_cache.php') continue;
    $content = file_get_contents($file);
    // Remove any existing ?v= query strings first, then add the new one
    $content = preg_replace('/href="admin_theme\.css(\?v=[0-9]+)?"/', 'href="admin_theme.css?v=' . $version . '"', $content);
    file_put_contents($file, $content);
    echo "Cache busted for " . basename($file) . "\n";
}
echo "Done.";
?>
