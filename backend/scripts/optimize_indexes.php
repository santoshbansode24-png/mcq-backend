<?php
// backend/scripts/optimize_indexes.php
// Script to audit and dynamically add missing indexes to the database

$basePath = dirname(__DIR__);
require_once $basePath . '/config/db.php';

echo "<h1>Database Index Optimization Report</h1>";
echo "<pre>";

$indexesToEnsure = [
    // format: [ 'table_name', 'index_name', 'columns' ]
    
    // 1. Study Planner
    [ 'study_tasks', 'idx_user_date_status', 'user_id, task_date, status' ],
    [ 'study_plans', 'idx_user_id', 'user_id' ],
    
    // 2. AI PDF Jobs
    [ 'pdf_study_jobs', 'idx_status_updated', 'status, updated_at' ],
    [ 'pdf_study_jobs', 'idx_user_folder_status', 'user_id, folder_id, status' ],
    [ 'pdf_study_folders', 'idx_user_parent', 'user_id, parent_id' ],
    
    // 3. User Progress & Interactions
    [ 'mcq_attempts', 'idx_user_chapter', 'user_id, chapter_id' ],
    [ 'student_progress', 'idx_user_chapter', 'user_id, chapter_id' ],
    [ 'bookmarks', 'idx_user_content', 'user_id, content_type' ],
    
    // 4. Content hierarchies (just in case foreign keys were dropped or query plans are bad)
    [ 'subjects', 'idx_class_id', 'class_id' ],
    [ 'chapters', 'idx_subject_order', 'subject_id, chapter_order' ],
];

foreach ($indexesToEnsure as $idxInfo) {
    $table = $idxInfo[0];
    $indexName = $idxInfo[1];
    $columns = $idxInfo[2];

    try {
        // Step 1: Check if the table exists
        $stmtTable = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmtTable->execute([$table]);
        if ($stmtTable->rowCount() === 0) {
            echo "[SKIPPED] Table '$table' does not exist.\n";
            continue;
        }

        // Step 2: Check if the index exists
        $stmtIdx = $pdo->prepare("SHOW INDEX FROM $table WHERE Key_name = ?");
        $stmtIdx->execute([$indexName]);
        
        if ($stmtIdx->rowCount() > 0) {
            echo "[OK] Index '$indexName' already exists on '$table'.\n";
        } else {
            // Step 3: Add the index
            echo "[ADDING] Adding index '$indexName' ($columns) to '$table'...\n";
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)");
            echo "   -> Success!\n";
        }
    } catch (PDOException $e) {
        echo "[ERROR] Failed processing '$indexName' on '$table': " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
echo "<h2>✅ Optimization Sweep Completed.</h2>";
?>
