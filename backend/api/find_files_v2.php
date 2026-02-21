<?php
$root_uploads = "/app/uploads";
echo "Listing $root_uploads:\n";
if (is_dir($root_uploads)) {
    print_r(scandir($root_uploads));
    $notes = "$root_uploads/notes";
    if (is_dir($notes)) {
        echo "\nListing $notes:\n";
        print_r(scandir($notes));
    }
} else {
    echo "$root_uploads not found\n";
}

$parent = "/app/..";
echo "\nListing $parent:\n";
if (is_dir($parent)) {
    print_r(scandir($parent));
}
?>
