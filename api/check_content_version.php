<?php
/**
 * Check Content Version - Returns current content version for SmartCache
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/db.php';

$board_type = $_GET['board_type'] ?? 'all';

try {
    $version = 1;
    if ($board_type !== 'all' && !empty($board_type)) {
        // Targeted version per board
        $stmt = $pdo->prepare("
            SELECT MAX(last_update) as last_update FROM (
                SELECT MAX(ch.created_at) as last_update 
                FROM chapters ch
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
                UNION ALL
                SELECT MAX(n.created_at) as last_update 
                FROM notes n
                JOIN chapters ch ON n.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
                UNION ALL
                SELECT MAX(m.created_at) as last_update 
                FROM mcqs m
                JOIN chapters ch ON m.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
                UNION ALL
                SELECT MAX(f.created_at) as last_update 
                FROM flashcards f
                JOIN chapters ch ON f.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
                UNION ALL
                SELECT MAX(qr.created_at) as last_update 
                FROM quick_revision qr
                JOIN chapters ch ON qr.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
                UNION ALL
                SELECT MAX(v.created_at) as last_update 
                FROM videos v
                JOIN chapters ch ON v.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.board_type = ?
            ) as t
        ");
        $stmt->execute([$board_type, $board_type, $board_type, $board_type, $board_type, $board_type]);
        $row = $stmt->fetch();
        if ($row && $row['last_update']) {
            $version = strtotime($row['last_update']);
        }
    } else {
        $stmt = $pdo->query("
            SELECT MAX(last_update) as last_update FROM (
                SELECT MAX(created_at) as last_update FROM chapters
                UNION ALL
                SELECT MAX(created_at) as last_update FROM notes
                UNION ALL
                SELECT MAX(created_at) as last_update FROM mcqs
                UNION ALL
                SELECT MAX(created_at) as last_update FROM flashcards
                UNION ALL
                SELECT MAX(created_at) as last_update FROM quick_revision
                UNION ALL
                SELECT MAX(created_at) as last_update FROM videos
            ) as t
        ");
        $row = $stmt->fetch();
        if ($row && $row['last_update']) {
            $version = strtotime($row['last_update']);
        }
    }
    
    echo json_encode(['status' => 'success', 'version' => $version, 'board_type' => $board_type]);
} catch (Exception $e) {
    echo json_encode(['status' => 'success', 'version' => time()]);
}
?>