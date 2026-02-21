<?php
$root = "/app";
echo "Listing $root:\n";
if (is_dir($root)) {
    print_r(scandir($root));
    
    $uploads = "$root/uploads";
    if (is_dir($uploads)) {
        echo "Listing $uploads:\n";
        print_r(scandir($uploads));
        $notes = "$uploads/notes";
        if (is_dir($notes)) {
            echo "\nListing $notes:\n";
            print_r(scandir($notes));
        }
    } else {
        echo "$uploads not found\n";
    }
}
?>
