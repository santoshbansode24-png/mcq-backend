<?php
/**
 * Fix Missing CLASS 3 on Railway (Production) Database
 * Run this on the Railway server to insert CLASS 3 for STATE_MARATHI and STATE_SEMI
 */
require 'backend/config/db.php';

$results = [];

// Check which DB we're on
$stmt = $pdo->query("SELECT DATABASE() as db");
$db = $stmt->fetch(PDO::FETCH_ASSOC);
$results[] = "🔍 Connected to database: " . $db['db'];

// Check current CLASS 3 entries
$stmt = $pdo->query("SELECT class_id, class_name, board_type FROM classes WHERE class_name IN ('CLASS 3','Class 3') ORDER BY class_id");
$existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
$results[] = "📋 Existing CLASS 3 entries: " . json_encode($existing);

// Insert CLASS 3 for STATE_MARATHI if missing
$hasMarathi = false;
$hasSemi = false;
foreach ($existing as $row) {
    if ($row['board_type'] === 'STATE_MARATHI') $hasMarathi = true;
    if ($row['board_type'] === 'STATE_SEMI') $hasSemi = true;
}

if (!$hasMarathi) {
    try {
        $pdo->exec("INSERT INTO classes (class_name, board_type) VALUES ('CLASS 3', 'STATE_MARATHI')");
        $results[] = "✅ Inserted CLASS 3 for STATE_MARATHI";
    } catch (PDOException $e) {
        $results[] = "❌ Failed STATE_MARATHI: " . $e->getMessage();
    }
} else {
    $results[] = "ℹ️ CLASS 3 for STATE_MARATHI already exists.";
}

if (!$hasSemi) {
    try {
        $pdo->exec("INSERT INTO classes (class_name, board_type) VALUES ('CLASS 3', 'STATE_SEMI')");
        $results[] = "✅ Inserted CLASS 3 for STATE_SEMI";
    } catch (PDOException $e) {
        $results[] = "❌ Failed STATE_SEMI: " . $e->getMessage();
    }
} else {
    $results[] = "ℹ️ CLASS 3 for STATE_SEMI already exists.";
}

// Final verification
$stmt = $pdo->query("SELECT class_id, class_name, board_type FROM classes WHERE class_name IN ('CLASS 3','Class 3') ORDER BY class_id");
$final = $stmt->fetchAll(PDO::FETCH_ASSOC);
$results[] = "✅ FINAL CLASS 3 entries: " . json_encode($final);

echo "<pre>" . implode("\n", $results) . "</pre>";
?>
