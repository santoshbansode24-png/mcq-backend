<?php
require_once 'config/db.php';

try {
    // Fetch all revisions
    $stmt = $pdo->query("SELECT revision_id, key_points FROM quick_revision");
    $revisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updatedCount = 0;
    
    foreach ($revisions as $rev) {
        $points = json_decode($rev['key_points'], true);
        $modified = false;
        
        if (is_array($points)) {
            foreach ($points as &$point) {
                // If 'e' (explanation) is missing or empty, add a default one
                if (!isset($point['e']) || empty($point['e'])) {
                    // Create a contextual explanation
                    $point['e'] = "Detailed explanation: " . $point['a'] . " is the correct answer because it fits the context of " . $point['q'];
                    $modified = true;
                }
            }
        }
        
        if ($modified) {
            $newJson = json_encode($points);
            $updateStmt = $pdo->prepare("UPDATE quick_revision SET key_points = ? WHERE revision_id = ?");
            $updateStmt->execute([$newJson, $rev['revision_id']]);
            $updatedCount++;
        }
    }
    
    echo "Successfully updated $updatedCount revisions with sample explanations.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
