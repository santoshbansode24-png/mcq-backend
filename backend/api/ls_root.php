<?php
$root = "/app";
echo "Listing $root:\n";
if (is_dir($root)) {
    print_r(scandir($root));
    
    $uploads = "$root/uploads";
    if (is_dir($uploads)) {
        echo "Listing $uploads:\n";
        print_r(scandir($uploads));
    } else {
        echo "$uploads not found\n";
    }
}
?>
