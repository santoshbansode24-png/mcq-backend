<?php 
require_once 'AiUsageManager.php';
try {
    $am = new AiUsageManager(16);
    $res = $am->canMakeRequest();
    echo "Can proceed: " . ($res === true ? "YES" : $res);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
?>
