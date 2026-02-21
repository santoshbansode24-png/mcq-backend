<?php
echo "Searching for all PDF files in /app...\n";
$output = shell_exec('find /app -name "*.pdf" 2>&1');
echo $output;

echo "\nSearching for all PDF files in current directory...\n";
$output2 = shell_exec('find . -name "*.pdf" 2>&1');
echo $output2;
?>
