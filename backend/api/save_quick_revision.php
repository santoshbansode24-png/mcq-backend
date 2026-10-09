<?php
/**
 * Save / Upload Quick Revision API
 * Supports JSON payload & CSV file upload in English, Marathi, Hindi
 * Veeru
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Veeru-Admin-Auth');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Only POST method is allowed');
    exit();
}

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection object (\$pdo) not found.");
    }

    $pdo->exec("SET NAMES utf8mb4");

    // Check JSON body or Form POST
    $inputRaw = file_get_contents('php://input');
    $body = json_decode($inputRaw, true) ?: [];

    $chapter_id = intval($body['chapter_id'] ?? $_POST['chapter_id'] ?? 0);
    $lang = strtolower(trim($body['lang'] ?? $_POST['lang'] ?? 'en'));
    $title = trim($body['title'] ?? $_POST['title'] ?? '');
    $summary = trim($body['summary'] ?? $_POST['summary'] ?? '');
    $key_points = $body['key_points'] ?? [];

    if ($chapter_id <= 0) {
        sendResponse('error', 'Valid chapter_id is required');
        exit();
    }

    // Check if chapter exists
    $chCheck = $pdo->prepare("SELECT chapter_name FROM chapters WHERE chapter_id = ?");
    $chCheck->execute([$chapter_id]);
    $chName = $chCheck->fetchColumn();
    if (!$chName) {
        sendResponse('error', "Chapter with ID $chapter_id not found");
        exit();
    }

    if (empty($title)) {
        $title = $chName . " - Quick Revision";
    }

    // Handle CSV upload if sent via $_FILES
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csvContent = file_get_contents($_FILES['csv_file']['tmp_name']);
        
        // Strip BOM
        $bom = pack('H*', 'EFBBBF');
        $csvContent = preg_replace("/^$bom/", '', $csvContent);
        if (!mb_check_encoding($csvContent, 'UTF-8')) {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'auto');
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $rowNum = 0;
        $key_points = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rowNum++;
            if (empty($row) || count($row) < 2) continue;
            $q = trim($row[0] ?? '');
            $a = trim($row[1] ?? '');
            $e = trim($row[2] ?? '');

            // Skip header row
            if ($rowNum === 1 && (strtolower($q) === 'question' || strtolower($a) === 'answer')) {
                continue;
            }

            if (!empty($q) && !empty($a)) {
                $key_points[] = [
                    'q' => $q,
                    'a' => $a,
                    'e' => $e
                ];
            }
        }
        fclose($stream);
    }

    if (empty($key_points) || !is_array($key_points)) {
        sendResponse('error', 'key_points array or valid CSV file is required');
        exit();
    }

    // Normalize points
    $cleanPoints = [];
    foreach ($key_points as $pt) {
        if (!is_array($pt)) continue;
        $q = trim($pt['q'] ?? ($pt['Question'] ?? ($pt['question'] ?? '')));
        $a = trim($pt['a'] ?? ($pt['Answer'] ?? ($pt['answer'] ?? '')));
        $e = trim($pt['e'] ?? ($pt['Explanation'] ?? ($pt['explanation'] ?? '')));

        if (!empty($q) && !empty($a)) {
            $cleanPoints[] = [
                'q' => $q,
                'a' => $a,
                'e' => $e
            ];
        }
    }

    if (empty($cleanPoints)) {
        sendResponse('error', 'No valid Q&A pairs found in input');
        exit();
    }

    $jsonPoints = json_encode($cleanPoints, JSON_UNESCAPED_UNICODE);

    // Check if revision exists for this chapter
    $checkQ = $pdo->prepare("SELECT revision_id FROM quick_revision WHERE chapter_id = ?");
    $checkQ->execute([$chapter_id]);
    $existingId = $checkQ->fetchColumn();

    if ($existingId) {
        if ($lang === 'mr') {
            $stmt = $pdo->prepare("UPDATE quick_revision SET title_mr = ?, summary_mr = ?, key_points_mr = ?, created_at = NOW() WHERE revision_id = ?");
            $stmt->execute([$title, $summary, $jsonPoints, $existingId]);
        } elseif ($lang === 'hi') {
            $stmt = $pdo->prepare("UPDATE quick_revision SET title_hi = ?, summary_hi = ?, key_points_hi = ?, created_at = NOW() WHERE revision_id = ?");
            $stmt->execute([$title, $summary, $jsonPoints, $existingId]);
        } else {
            $stmt = $pdo->prepare("UPDATE quick_revision SET title = ?, summary = ?, key_points = ?, created_at = NOW() WHERE revision_id = ?");
            $stmt->execute([$title, $summary, $jsonPoints, $existingId]);
        }
        sendResponse('success', "Quick revision updated successfully for chapter $chapter_id ($lang)", [
            'revision_id' => $existingId,
            'chapter_id' => $chapter_id,
            'language' => $lang,
            'points_count' => count($cleanPoints)
        ]);
    } else {
        if ($lang === 'mr') {
            $stmt = $pdo->prepare("INSERT INTO quick_revision (chapter_id, title, summary, key_points, title_mr, summary_mr, key_points_mr) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$chapter_id, $title, $summary, $jsonPoints, $title, $summary, $jsonPoints]);
        } elseif ($lang === 'hi') {
            $stmt = $pdo->prepare("INSERT INTO quick_revision (chapter_id, title, summary, key_points, title_hi, summary_hi, key_points_hi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$chapter_id, $title, $summary, $jsonPoints, $title, $summary, $jsonPoints]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO quick_revision (chapter_id, title, summary, key_points) VALUES (?, ?, ?, ?)");
            $stmt->execute([$chapter_id, $title, $summary, $jsonPoints]);
        }
        $newId = $pdo->lastInsertId();
        sendResponse('success', "Quick revision created successfully for chapter $chapter_id ($lang)", [
            'revision_id' => $newId,
            'chapter_id' => $chapter_id,
            'language' => $lang,
            'points_count' => count($cleanPoints)
        ]);
    }

} catch (Exception $e) {
    sendResponse('error', 'Database error: ' . $e->getMessage());
}
