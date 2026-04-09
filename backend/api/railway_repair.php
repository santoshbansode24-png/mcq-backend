<?php
/**
 * Railway Deployment Repair Tool
 * Version: 3.5.1
 */
header('Content-Type: text/plain');
echo "REPAIR TOOL ACTIVE\n";
echo "Git Branch Tracking: " . (getenv('RAILWAY_GIT_BRANCH') ?: 'Unknown') . "\n";
echo "Commit Hash: " . (getenv('RAILWAY_GIT_COMMIT_SHA') ?: 'Unknown') . "\n";
echo "PWD: " . getcwd() . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "\nFiles in root:\n";
print_r(scandir('.'));
?>
