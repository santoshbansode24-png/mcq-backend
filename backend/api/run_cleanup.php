<?php
/**
 * Cleanup Script — Remove debug/test/check files
 * Run ONCE via browser: http://localhost/veeru/backend/api/run_cleanup.php
 * DELETE THIS FILE AFTER RUNNING.
 */

// Simple key guard — change this before running
$key = $_GET['key'] ?? '';
if ($key !== 'cleanup2025') {
    die('Access denied. Use ?key=cleanup2025');
}

$dir = __DIR__;

$filesToDelete = [
    // Debug files
    'debug_ai.php', 'debug_all_notes.php', 'debug_check_revisions.php',
    'debug_classes_check.php', 'debug_env.php', 'debug_files.php',
    'debug_latest_pdf.php', 'debug_missions.php', 'debug_path.php', 'debug_progress.php',
    // Test files
    'test_ai.php', 'test_ai_key.php', 'test_connection.php', 'test_cors.php',
    'test_db_connection.php', 'test_db_local.php', 'test_env.php', 'test_logging.ps1',
    'test_production_logic.php', 'test_registration_fix.php', 'test_save_progress.php',
    'test_upload.php', 'test_write.php',
    // Check files
    'check_chapter_details.php', 'check_commit.php', 'check_content_version.php',
    'check_files.php', 'check_latest_uploads.php', 'check_mcq_count.php',
    'check_progress.php', 'check_progress_db.php', 'check_reviewer.php',
    'check_s3.php', 'check_schema.php', 'check_src.php', 'check_stats.php', 'check_upload.php',
    // LS files
    'ls.php', 'ls_root.php', 'ls_uploads.php',
    // Fix + misc
    'fix_ai_table.php', 'fix_db.php', 'v25.php', 'view_log.php', 'view_login_logs.php',
    'show_google_proof.php', 'deep_find.php', 'find_boond_ch.php', 'find_ch133_notes.php',
    'find_external_notes.php', 'find_files.php', 'find_files_v2.php',
    // Big temp file
    'temp_test.pdf',
];

$deleted = [];
$notFound = [];

foreach ($filesToDelete as $file) {
    $path = $dir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path)) {
        if (unlink($path)) {
            $deleted[] = $file;
        } else {
            $notFound[] = $file . ' (failed to delete)';
        }
    } else {
        $notFound[] = $file . ' (not found)';
    }
}

// Also delete self after running
$selfDelete = __FILE__;

echo '<html><head><style>body{font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0}
.ok{color:#10b981}.err{color:#ef4444}.title{font-size:20px;font-weight:bold;margin-bottom:16px;color:#60a5fa}
</style></head><body>';
echo '<div class="title">🧹 Cleanup Results</div>';
echo '<p class="ok">✅ Deleted (' . count($deleted) . '):</p><ul>';
foreach ($deleted as $f) echo "<li class='ok'>$f</li>";
echo '</ul>';
if ($notFound) {
    echo '<p class="err">⚠️ Not found / issues (' . count($notFound) . '):</p><ul>';
    foreach ($notFound as $f) echo "<li class='err'>$f</li>";
    echo '</ul>';
}
echo '<p style="margin-top:20px;color:#f59e0b">⚠️ Delete <strong>run_cleanup.php</strong> manually after verifying!</p>';
echo '</body></html>';
?>
