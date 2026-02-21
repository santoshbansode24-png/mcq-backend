<?php
echo "Current Branch env: " . getenv('RAILWAY_GIT_BRANCH') . "\n";
echo "Current Commit env: " . getenv('RAILWAY_GIT_COMMIT_SHA') . "\n";
echo "Git Log (last 1):\n";
echo shell_exec('git log -n 1 --oneline');
?>
