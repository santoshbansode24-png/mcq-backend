<?php
/**
 * Get Quick Revision API (High-Performance, Normalized & Multi-Language)
 * Veeru
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse('error', 'Invalid request method');
    exit();
}

$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$lang = strtolower(trim($_GET['lang'] ?? $_GET['language'] ?? 'en'));

if ($chapter_id <= 0) {
    sendResponse('error', 'Invalid chapter ID');
    exit();
}

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection object (\$pdo) not found.");
    }
    
    // High-performance index seek: LIMIT 1 for latest revision
    $sql = "SELECT * FROM quick_revision WHERE chapter_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chapter_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        sendResponse('success', 'No revision notes available for this chapter', []);
        exit();
    }
    
    $title = $row['title'] ?? 'Revision';
    $summary = $row['summary'] ?? '';
    $kpRaw = $row['key_points'] ?? '[]';

    // Multi-Language Selection with Graceful Fallback
    if ($lang === 'hi') {
        if (!empty($row['title_hi'])) $title = $row['title_hi'];
        if (!empty($row['summary_hi'])) $summary = $row['summary_hi'];
        if (!empty($row['key_points_hi']) && $row['key_points_hi'] !== '[]') $kpRaw = $row['key_points_hi'];
    } elseif ($lang === 'mr') {
        if (!empty($row['title_mr'])) $title = $row['title_mr'];
        if (!empty($row['summary_mr'])) $summary = $row['summary_mr'];
        if (!empty($row['key_points_mr']) && $row['key_points_mr'] !== '[]') $kpRaw = $row['key_points_mr'];
    }

    $rawKeyPoints = is_array($kpRaw) ? $kpRaw : json_decode($kpRaw, true);
    // If selected language points are empty, fall back to any available language
    if (empty($rawKeyPoints) || !is_array($rawKeyPoints) || count($rawKeyPoints) === 0) {
        if (!empty($row['key_points_mr']) && $row['key_points_mr'] !== '[]') {
            $kpRaw = $row['key_points_mr'];
            if (!empty($row['title_mr'])) $title = $row['title_mr'];
            if (!empty($row['summary_mr'])) $summary = $row['summary_mr'];
        } elseif (!empty($row['key_points_hi']) && $row['key_points_hi'] !== '[]') {
            $kpRaw = $row['key_points_hi'];
            if (!empty($row['title_hi'])) $title = $row['title_hi'];
            if (!empty($row['summary_hi'])) $summary = $row['summary_hi'];
        } elseif (!empty($row['key_points']) && $row['key_points'] !== '[]') {
            $kpRaw = $row['key_points'];
        }
        $rawKeyPoints = is_array($kpRaw) ? $kpRaw : json_decode($kpRaw, true);
    }

    $normalizedPoints = [];

    if (is_array($rawKeyPoints)) {
        foreach ($rawKeyPoints as $point) {
            if (is_string($point)) {
                $cleaned = html_entity_decode(trim($point), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!empty($cleaned)) {
                    $normalizedPoints[] = [
                        'q' => $cleaned,
                        'a' => $cleaned,
                        'e' => ''
                    ];
                }
            } elseif (is_array($point)) {
                $q = $point['q'] ?? ($point['Question'] ?? ($point['question'] ?? ''));
                $a = $point['a'] ?? ($point['Answer'] ?? ($point['answer'] ?? ''));
                $e = $point['e'] ?? ($point['Explanation'] ?? ($point['explanation'] ?? ''));

                $q = html_entity_decode(trim($q), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $a = html_entity_decode(trim($a), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $e = html_entity_decode(trim($e), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if (!empty($q) || !empty($a)) {
                    $pointArr = [
                        'q' => $q ?: $a,
                        'a' => $a ?: $q,
                        'e' => $e
                    ];
                    if (!empty($point['opt_a'])) $pointArr['opt_a'] = html_entity_decode(trim($point['opt_a']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!empty($point['opt_b'])) $pointArr['opt_b'] = html_entity_decode(trim($point['opt_b']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!empty($point['correct'])) $pointArr['correct'] = trim($point['correct']);
                    $normalizedPoints[] = $pointArr;
                }
            }
        }
    }

    $cleanTitle = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cleanSummary = html_entity_decode(trim($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    sendResponse('success', 'Revision notes retrieved successfully', [
        'revision_id' => $row['revision_id'],
        'chapter_id'  => $row['chapter_id'],
        'title'       => $cleanTitle,
        'summary'     => $cleanSummary,
        'key_points'  => $normalizedPoints,
        'created_at'  => $row['created_at'],
        'language'    => $lang
    ]);

} catch (Exception $e) {
    sendResponse('error', 'An error occurred while fetching revision notes: ' . $e->getMessage());
}
?>