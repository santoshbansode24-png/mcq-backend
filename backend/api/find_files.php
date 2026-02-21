<?php
echo "Current Directory: " . __DIR__ . "\n";
$root = "/app";
echo "Listing $root:\n";
if (is_dir($root)) {
    print_r(scandir($root));
}
$backend = "/app/backend";
echo "\nListing $backend:\n";
if (is_dir($backend)) {
    print_r(scandir($backend));
}
$uploads = "/app/backend/uploads";
echo "\nListing $uploads:\n";
if (is_dir($uploads)) {
    print_r(scandir($uploads));
    $notes = $uploads . "/notes";
    if (is_dir($notes)) {
        echo "\nListing $notes:\n";
        print_r(scandir($notes));
    }
}
?>
