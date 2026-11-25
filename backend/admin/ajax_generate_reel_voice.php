<?php
/**
 * Admin AJAX Endpoint: Generate Natural Teacher Voiceover + R2 MP3s for a Quick Revision
 * Processes in small batches (default 3 items) to avoid HTTP timeouts.
 * Veeru
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/reel_voice_helper.php';

$revisionId = intval($_POST['revision_id'] ?? 0);
$lang       = strtolower(trim($_POST['lang'] ?? 'en'));
$batchSize  = min(5, max(1, intval($_POST['batch_size'] ?? 3)));

if ($revisionId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid revision_id.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT qr.*, s.subject_name
        FROM quick_revision qr
        JOIN chapters ch ON qr.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        WHERE qr.revision_id = ?
    ");
    $stmt->execute([$revisionId]);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rev) {
        throw new Exception('Revision not found.');
    }

    $col = $lang === 'mr' ? 'key_points_mr' : ($lang === 'hi' ? 'key_points_hi' : 'key_points');
    $points = json_decode($rev[$col] ?? '[]', true);
    if (!is_array($points) || empty($points)) {
        echo json_encode(['status' => 'success', 'processed' => 0, 'remaining' => 0, 'message' => 'No items found for this language.']);
        exit();
    }

    $subjectName = $rev['subject_name'] ?? '';
    $processed = 0;

    foreach ($points as $idx => $item) {
        if (!is_array($item)) continue;
        $hasUrls = !empty($item['step_audio_urls']) && is_array($item['step_audio_urls']) && count(array_filter($item['step_audio_urls'])) > 0;
        if ($hasUrls) continue;

        if ($processed >= $batchSize) break;

        $points[$idx] = enrichQuickRevisionItemWithVoiceover($item, $lang, $subjectName, true);
        $processed++;
    }

    if ($processed > 0) {
        $upd = $pdo->prepare("UPDATE quick_revision SET {$col} = ? WHERE revision_id = ?");
        $upd->execute([json_encode($points, JSON_UNESCAPED_UNICODE), $revisionId]);
    }

    $remaining = 0;
    foreach ($points as $item) {
        if (!is_array($item)) continue;
        $hasUrls = !empty($item['step_audio_urls']) && is_array($item['step_audio_urls']) && count(array_filter($item['step_audio_urls'])) > 0;
        if (!$hasUrls) $remaining++;
    }

    echo json_encode([
        'status'    => 'success',
        'processed' => $processed,
        'remaining' => $remaining,
        'message'   => "Generated teacher voiceover + R2 audio for {$processed} items. Remaining: {$remaining}."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
