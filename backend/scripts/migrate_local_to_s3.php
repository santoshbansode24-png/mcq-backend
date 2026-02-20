<?php
/**
 * Local to S3 Migration Script
 * Veeru
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/aws-config.php';

echo "🚀 Starting Migration: Local Files -> AWS S3\n";
echo "-------------------------------------------\n";

// 1. Find all notes using local file paths
$stmt = $pdo->prepare("SELECT note_id, title, file_path FROM notes WHERE file_path LIKE 'uploads/notes/%'");
$stmt->execute();
$local_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($local_notes)) {
    echo "✅ No local files found to migrate. All files seem to be on cloud or URLs.\n";
    exit();
}

echo "📦 Found " . count($local_notes) . " notes to migrate.\n\n";

$success_count = 0;
$fail_count = 0;

foreach ($local_notes as $note) {
    $note_id = $note['note_id'];
    $title = $note['title'];
    $local_path = $note['file_path']; // e.g., uploads/notes/file.pdf
    
    // Construct local absolute path
    $absolute_local_path = __DIR__ . '/../../' . $local_path;
    
    echo "⏳ Migrating: $title ($local_path)... ";
    
    if (!file_exists($absolute_local_path)) {
        echo "❌ FAILED (Local file missing at: $absolute_local_path)\n";
        $fail_count++;
        continue;
    }

    // Generate S3 Key
    $filename = basename($local_path);
    $s3_key = "notes/" . $filename;
    
    // 2. Upload to S3
    $s3_url = uploadToS3($absolute_local_path, $s3_key);
    
    if ($s3_url) {
        // 3. Update Database
        $update = $pdo->prepare("UPDATE notes SET file_path = ? WHERE note_id = ?");
        $update->execute([$s3_url, $note_id]);
        
        echo "✅ SUCCESS (Uploaded to S3)\n";
        $success_count++;
    } else {
        echo "❌ FAILED (AWS Upload failed. Check keys in config/aws-config.php)\n";
        $fail_count++;
    }
}

echo "\n-------------------------------------------\n";
echo "🏁 Migration Finished!\n";
echo "✅ Successful: $success_count\n";
echo "❌ Failed: $fail_count\n";
echo "-------------------------------------------\n";

if ($success_count > 0) {
    echo "💡 PRO TIP: Now Export your 'notes' table to Railway to make these links live in your app!\n";
}
?>
