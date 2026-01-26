<?php
/**
 * Get English Missions API
 * Returns list of missions with user progress
 */

require_once __DIR__ . '/cors_middleware.php';
require_once __DIR__ . '/../config/db.php';

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    sendResponse('error', 'Valid user_id is required', null, 400);
}

try {
    // 1. Get all missions
    $stmt = $pdo->query("SELECT * FROM english_missions ORDER BY level_id ASC");
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get user progress
    $stmt = $pdo->prepare("SELECT * FROM user_english_progress WHERE user_id = ?");
    $stmt->execute([$userId]);
    $progressRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create progress map
    $progressMap = [];
    $highestCompleted = 0;
    
    foreach ($progressRows as $row) {
        $progressMap[$row['level_id']] = $row;
        if ($row['is_completed']) {
            $highestCompleted = max($highestCompleted, $row['level_id']);
        }
    }

    // 3. Merge data
    foreach ($missions as &$mission) {
        $lid = $mission['level_id'];
        
        // Check local progress
        $p = isset($progressMap[$lid]) ? $progressMap[$lid] : null;
        
        $mission['is_completed'] = $p ? (bool)$p['is_completed'] : false;
        $mission['stars'] = $p ? (int)$p['stars'] : 0;
        $mission['high_score'] = $p ? (int)$p['fluency_score'] : 0;
        
        // Unlock logic: Level 1 is always open. Level N is open if Level N-1 is completed.
        if ($lid == 1) {
            $mission['is_locked'] = false;
        } else {
            $prevCompleted = isset($progressMap[$lid - 1]) && $progressMap[$lid - 1]['is_completed'];
            $mission['is_locked'] = !$prevCompleted;
        }
        
        // Clean vocab json
        if ($mission['target_vocab_json']) {
            $mission['target_vocab'] = json_decode($mission['target_vocab_json']);
        }
        unset($mission['target_vocab_json']);
        unset($mission['system_prompt']); // Don't send prompt to frontend security
    }

    sendResponse('success', 'Missions retrieved', $missions);

} catch (PDOException $e) {
    sendResponse('error', 'DB Error: ' . $e->getMessage(), null, 500);
}
?>
