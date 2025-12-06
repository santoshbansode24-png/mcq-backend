<?php
// Print current directory Listing
$files = scandir(__DIR__);
echo "<h1>File Structure Debug</h1>";
echo "<pre>";
print_r($files);
echo "</pre>";

// Recursive scan to find where 'login.php' is hiding
function find_login($dir) {
    if(!is_dir($dir)) return;
    $scan = scandir($dir);
    foreach($scan as $file) {
        if($file == '.' || $file == '..') continue;
        if($file == 'login.php') echo "<h2>FOUND IT: $dir/$file</h2>";
        if(is_dir("$dir/$file")) find_login("$dir/$file");
    }
}
echo "Searching for login.php...<br>";
find_login(__DIR__);

// Print ENV vars
echo "<h1>Environment Variables</h1>";
echo "<pre>";
print_r(getenv());
print_r($_ENV);
echo "</pre>";
?>
