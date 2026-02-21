<?php
$target = dirname(__DIR__) . '/uploads/notes/test_write.txt';
echo "Attempting to write to $target...\n";
if (file_put_contents($target, "Test write at " . date('Y-m-d H:i:s'))) {
    echo "✅ SUCCESS: File written.\n";
    echo "Contents: " . file_get_contents($target) . "\n";
    unlink($target);
    echo "✅ SUCCESS: File deleted.\n";
} else {
    echo "❌ ERROR: Failed to write to $target. Check permissions.\n";
}
?>
