<?php
/**
 * Get PDF Study Job Status
 * Optimized for Veeru App: Mobile Polling & Bandwidth Efficiency
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate'); // Prevent stale status

require_once __DIR__ . '/../config/db.php';

// Inputs
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$folder_id = isset($_GET['folder_id']) ? $_GET['folder_id'] : null;

if (!$user_id) {
    echo json_encode(['status' => 'success', 'data' => [], 'count' => 0]);
    exit();
}

try {
    if ($job_id > 0) {
        /**
         * SINGLE JOB STATUS CHECK
         * Fetching metadata only first to see if status is 'completed'
         */
        $stmt = $pdo->prepare("
            SELECT job_id, file_name, status, progress, error_message, updated_at 
            FROM pdf_study_jobs 
            WHERE job_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$job_id, $user_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            throw new Exception("Study session not found.");
        }

        // --- SELF-HEALING AUTO-RECOVERY FOR 5% STUCK JOBS ---
        // If status is 'pending' or stuck in 'processing' without updates for > 15s,
        // trigger worker in-line to process this job immediately!
        $updatedTs = !empty($job['updated_at']) ? strtotime($job['updated_at']) : 0;
        $isPending = ($job['status'] === 'pending');
        $isStuckProcessing = ($job['status'] === 'processing' && (time() - $updatedTs) > 15);

        if ($isPending || $isStuckProcessing) {
            if (!defined('WORKER_SECRET')) {
                define('WORKER_SECRET', 'veeru_ai_worker_v2_secure_ping');
            }
            $_GET['key'] = WORKER_SECRET;
            $_GET['force_job_id'] = $job_id;

            ob_start();
            try {
                include __DIR__ . '/pdf_worker_ai.php';
            } catch (Throwable $t) {
                error_log("[Veeru Self-Healing Worker Error]: " . $t->getMessage());
            }
            ob_end_clean();

            // Re-fetch updated job from DB
            $stmt->execute([$job_id, $user_id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Only fetch and decode JSON if the job is finished
        if ($job['status'] === 'completed') {
            $stmtContent = $pdo->prepare("SELECT study_pack_json FROM pdf_study_content WHERE job_id = ?");
            $stmtContent->execute([$job_id]);
            $content = $stmtContent->fetch(PDO::FETCH_ASSOC);

            if ($content) {
                // Decode to verify validity, but keep as array for the final response
                $job['study_pack'] = json_decode($content['study_pack_json'], true);
            }
        }

        echo json_encode(['status' => 'success', 'data' => $job]);

    } else {
        /**
         * GET JOB LIST (History)
         * Optimized for speed: No heavy text/JSON blobs allowed here.
         */
        $conditions = ["user_id = ?"];
        $params = [$user_id];

        // Folder Logic
        if ($folder_id === 'root') {
            $conditions[] = "(folder_id IS NULL OR folder_id = 0)";
        } else if ($folder_id !== null && is_numeric($folder_id)) {
            $conditions[] = "folder_id = ?";
            $params[] = intval($folder_id);
        }

        $whereClause = implode(" AND ", $conditions);

        $sql = "SELECT job_id, folder_id, file_name, status, progress, error_message, created_at 
                FROM pdf_study_jobs 
                WHERE $whereClause
                ORDER BY created_at DESC 
                LIMIT 30"; // Reduced limit for faster mobile rendering

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- SELF-HEALING AUTO-RECOVERY FOR LIST VIEW ---
        // Automatically process the first pending job inline when client polls status
        $pendingJobId = 0;
        foreach ($jobs as $j) {
            if ($j['status'] === 'pending') {
                $pendingJobId = intval($j['job_id']);
                break;
            }
        }

        if ($pendingJobId > 0) {
            if (!defined('WORKER_SECRET')) {
                define('WORKER_SECRET', 'veeru_ai_worker_v2_secure_ping');
            }
            $_GET['key'] = WORKER_SECRET;
            $_GET['force_job_id'] = $pendingJobId;

            ob_start();
            try {
                include __DIR__ . '/pdf_worker_ai.php';
            } catch (Throwable $t) {
                error_log("[Veeru Self-Healing Worker List Error]: " . $t->getMessage());
            }
            ob_end_clean();

            // Re-fetch updated job list from DB
            $stmt->execute($params);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status' => 'success',
            'count' => count($jobs),
            'data' => $jobs
        ]);
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request for logical errors
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>