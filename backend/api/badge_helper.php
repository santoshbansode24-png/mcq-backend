<?php
/**
 * Badge Checker Helper
 * 
 * Functions to check and award badges
 */

function checkAndAwardBadges($pdo, $user_id, $trigger_type, $data = []) {
    $awarded_badges = [];
    
    // Fetch all unearned badges for this user
    $stmt = $pdo->prepare("
        SELECT * FROM badges 
        WHERE badge_id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?)
    ");
    $stmt->execute([$user_id]);
    $badges = $stmt->fetchAll();
    
    foreach ($badges as $badge) {
        $earned = false;
        
        switch ($badge['criteria_type']) {
            case 'time_of_day':
                if ($trigger_type === 'login' || $trigger_type === 'activity') {
                    $current_hour = date('H:i');
                    if ($current_hour >= $badge['criteria_value']) {
                        $earned = true;
                    }
                }
                break;
                
            case 'login_streak':
                if ($trigger_type === 'login') {
                    // Check user's streak
                    $streakStmt = $pdo->prepare("SELECT login_streak FROM users WHERE user_id = ?");
                    $streakStmt->execute([$user_id]);
                    $user = $streakStmt->fetch();
                    if ($user && $user['login_streak'] >= intval($badge['criteria_value'])) {
                        $earned = true;
                    }
                }
                break;
                
            case 'perfect_scores':
                if ($trigger_type === 'quiz_submission') {
                    // Count perfect scores
                    $scoreStmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM student_progress 
                        WHERE user_id = ? AND percentage = 100
                    ");
                    $scoreStmt->execute([$user_id]);
                    $result = $scoreStmt->fetch();
                    if ($result && $result['count'] >= intval($badge['criteria_value'])) {
                        $earned = true;
                    }
                }
                break;
        }
        
        if ($earned) {
            // Award badge
            $insertStmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            try {
                $insertStmt->execute([$user_id, $badge['badge_id']]);
                $awarded_badges[] = $badge;
            } catch (PDOException $e) {
                // Ignore duplicate errors
            }
        }
    }
    
    return $awarded_badges;
}
?>
