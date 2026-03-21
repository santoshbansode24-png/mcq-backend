<?php
/**
 * Async Quiz Generation Start
 * Initiates a long-running AI task and returns a Task ID.
 */

// 1. Headers & Setup
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(); }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/AiUsageManager.php';
require_once __DIR__ . '/AiTaskManager.php';

// Composer logic
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

$taskManager = new AiTaskManager($pdo);

try {
    // SELF-HEALING: Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_type VARCHAR(50) NOT NULL,
        status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
        request_payload TEXT,
        result_data MEDIUMTEXT,
        error_message TEXT,
        progress INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($userId <= 0) throw new Exception("Unauthorized: Valid User ID required.");

    // Check usage limits
    $aiManager = new AiUsageManager($userId);
    $canProceed = $aiManager->canMakeRequest();
    if ($canProceed !== true) throw new Exception($canProceed);

    // Prepare Task Data
    $inputType = $_POST['input_type'] ?? '';
    $difficulty = $_POST['difficulty'] ?? 'Medium';
    $language = $_POST['language'] ?? 'English';
    
    $savedFilePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/temp_tasks/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $savedFilePath = $uploadDir . time() . '_' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $savedFilePath);
    }

    $payload = [
        'user_id' => $userId,
        'input_type' => $inputType,
        'difficulty' => $difficulty,
        'language' => $language,
        'content' => $_POST['content'] ?? '',
        'file_path' => $savedFilePath ? realpath($savedFilePath) : null,
        'mime_type' => $savedFilePath ? mime_content_type($savedFilePath) : null
    ];

    // Create Task
    $taskId = $taskManager->createTask($userId, 'quiz_generation', $payload);

    // --- CLEAN RESPONSE ---
    $response = json_encode([
        "status" => "success",
        "message" => "Task started",
        "task_id" => $taskId
    ]);

    // Close the connection to the client
    ignore_user_abort(true);
    set_time_limit(600); // 10 minutes

    // Trick to send response and close connection while keeping script running
    header("Content-Length: " . strlen($response));
    header("Connection: close");
    echo $response;
    
    // Flush all output
    if (ob_get_level()) ob_end_flush();
    flush();
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // --- BACKGROUND PROCESSING STARTS HERE ---
    // Launch via CLI to avoid blocking the single-threaded Railway PHP development server
    $scriptUrl = $_SERVER['DOCUMENT_ROOT'] . '/backend/api/ai_generate_quiz_worker.php';
    if (!file_exists($scriptUrl)) {
        // Fallback for different DOCUMENT_ROOT configurations
        $scriptUrl = __DIR__ . '/ai_generate_quiz_worker.php';
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("start /B C:\\xampp\\php\\php.exe " . escapeshellarg($scriptUrl) . " " . $taskId, "r"));
    } else {
        exec("php " . escapeshellarg($scriptUrl) . " " . $taskId . " > /dev/null 2>&1 &");
    }
} catch (Exception $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(200); // Return JSON even on error
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
