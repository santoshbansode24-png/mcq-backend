<?php
/**
 * AI Task Manager
 * Handles background jobs for long-running AI tasks like Quiz Generation.
 */

class AiTaskManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Create a new task
     */
    public function createTask($userId, $taskType, $payload) {
        $stmt = $this->pdo->prepare("INSERT INTO ai_tasks (user_id, task_type, request_payload, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$userId, $taskType, json_encode($payload)]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update task status and progress
     */
    public function updateTask($taskId, $status, $progress = 0, $result = null, $error = null) {
        $sql = "UPDATE ai_tasks SET status = ?, progress = ?";
        $params = [$status, $progress];

        if ($result !== null) {
            $sql .= ", result_data = ?";
            $params[] = is_string($result) ? $result : json_encode($result);
        }

        if ($error !== null) {
            $sql .= ", error_message = ?";
            $params[] = $error;
        }

        $sql .= " WHERE id = ?";
        $params[] = $taskId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get task status
     */
    public function getTask($taskId) {
        $stmt = $this->pdo->prepare("SELECT * FROM ai_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
