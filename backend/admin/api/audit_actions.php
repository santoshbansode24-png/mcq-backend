<?php
/**
 * Audit Center API
 * Veeru - Secured with Board Isolation
 */
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_SESSION['admin_selected_board'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Board not selected']);
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'list') {
        $type = $_GET['type'] ?? 'mcq';
        $class_id = intval($_GET['class_id'] ?? 0);
        $subject_id = intval($_GET['subject_id'] ?? 0);
        $status = $_GET['status'] ?? 'pending';

        $table = '';
        $id_col = '';
        if ($type === 'mcq') { $table = 'mcqs'; $id_col = 'mcq_id'; }
        elseif ($type === 'flashcard') { $table = 'flashcards'; $id_col = 'id'; } // In DB it is id, not card_id
        elseif ($type === 'revision') { $table = 'quick_revision'; $id_col = 'revision_id'; }
        else { 
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
            exit();
        }

        // Verify that the requested class belongs to the selected board
        $verify_class = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_id = ? AND board_type = ?");
        $verify_class->execute([$class_id, $selected_board]);
        if ($verify_class->fetchColumn() == 0) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized board class access']);
            exit();
        }

        $query = "
            SELECT t.*, $id_col as id 
            FROM $table t
            JOIN chapters ch ON t.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.class_id = ? AND c.board_type = ?
        ";
        $params = [$class_id, $selected_board];

        if ($subject_id > 0) {
            $query .= " AND s.subject_id = ?";
            $params[] = $subject_id;
        }

        if ($status === 'pending') {
            $query .= " AND (t.status IS NULL OR t.status = 'pending')";
        } elseif ($status === 'flagged') {
            $query .= " AND t.status = 'flagged'";
        } elseif ($status === 'verified') {
            $query .= " AND t.status = 'verified'";
        }

        $query .= " ORDER BY $id_col DESC LIMIT 100";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit();
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $type = $input['type'] ?? 'mcq';
    $id = intval($input['id'] ?? 0);

    $table = '';
    $id_col = '';
    if ($type === 'mcq') { $table = 'mcqs'; $id_col = 'mcq_id'; }
    elseif ($type === 'flashcard') { $table = 'flashcards'; $id_col = 'id'; } // In DB it is id, not card_id
    elseif ($type === 'revision') { $table = 'quick_revision'; $id_col = 'revision_id'; }
    else { 
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
        exit();
    }

    // Verify ownership of the item before taking action
    $verify_sql = "
        SELECT t.$id_col 
        FROM $table t
        JOIN chapters ch ON t.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE t.$id_col = ? AND c.board_type = ?
    ";
    $check = $pdo->prepare($verify_sql);
    $check->execute([$id, $selected_board]);
    if (!$check->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Item not found or access denied']);
        exit();
    }

    if ($action === 'verify') {
        $stmt = $pdo->prepare("UPDATE $table SET status = 'verified', admin_feedback = NULL WHERE $id_col = ?");
        $stmt->execute([$id]);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Verified']);
    }

    elseif ($action === 'flag') {
        $feedback = strip_tags(trim($input['feedback'] ?? ''));
        $stmt = $pdo->prepare("UPDATE $table SET status = 'flagged', admin_feedback = ? WHERE $id_col = ?");
        $stmt->execute([$feedback, $id]);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Flagged']);
    }

    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col = ?");
        $stmt->execute([$id]);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Deleted']);
    }
    exit();
}
?>
