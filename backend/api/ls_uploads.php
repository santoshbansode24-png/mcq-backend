<?php
$dir = dirname(__DIR__) . '/uploads';
echo "Listing $dir:\n";
if (is_dir($dir)) {
    print_r(scandir($dir));
    
    $notes_dir = $dir . '/notes';
    echo "\nListing $notes_dir:\n";
    if (is_dir($notes_dir)) {
        print_r(scandir($notes_dir));
    } else {
        echo "notes/ directory not found!\n";
    }
} else {
    echo "uploads/ directory not found!\n";
}
?>
