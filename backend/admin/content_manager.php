<?php
/**
 * Content Manager - Admin Panel
 * Filter, View, and Delete Content
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

require_once '../config/db.php';

// ==========================================
// HANDLE AJAX REQUESTS
// ==========================================

// 1. GET CONTENT
if (isset($_GET['action']) && $_GET['action'] == 'get_content') {
    $chapter_id = intval($_GET['chapter_id']);
    $type = $_GET['type'];
    
    $data = [];
    
    try {
        // Enforce board security for get_content
        $board_join = "JOIN chapters ch ON t.chapter_id = ch.chapter_id JOIN subjects s ON ch.subject_id = s.subject_id JOIN classes c ON s.class_id = c.class_id";
        $board_where = "t.chapter_id = ? AND c.board_type = ?";
        $params = [$chapter_id, $selected_board];

        if ($type == 'mcqs') {
            $stmt = $pdo->prepare("SELECT t.mcq_id as id, t.question as title, t.difficulty as subtitle FROM mcqs t $board_join WHERE $board_where ORDER BY t.mcq_id DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'notes') {
            $stmt = $pdo->prepare("SELECT t.note_id as id, t.title, t.file_path as subtitle FROM notes t $board_join WHERE $board_where ORDER BY t.note_id DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'videos') {
            $stmt = $pdo->prepare("SELECT t.video_id as id, t.title, t.url as subtitle FROM videos t $board_join WHERE $board_where ORDER BY t.video_id DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'flashcards') {
            $stmt = $pdo->prepare("SELECT t.id as id, t.question_front as title, t.answer_back as subtitle FROM flashcards t $board_join WHERE $board_where ORDER BY t.id DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'quick_revision') {
            $stmt = $pdo->prepare("SELECT t.revision_id as id, t.title, LEFT(t.summary, 50) as subtitle FROM quick_revision t $board_join WHERE $board_where ORDER BY t.revision_id DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// 2. DELETE CONTENT
if (isset($_POST['action']) && $_POST['action'] == 'delete_content') {
    $id = intval($_POST['id']);
    $type = $_POST['type'];
    
    // Check for Board Selection (Ajax Context)
    if (!isset($_SESSION['admin_selected_board'])) {
        echo json_encode(['status' => 'error', 'message' => 'Board not selected']);
        exit();
    }
    $selected_board = $_SESSION['admin_selected_board'];
    
    $table_map = [
        'mcqs' => 'mcqs',
        'notes' => 'notes',
        'videos' => 'videos',
        'flashcards' => 'flashcards',
        'quick_revision' => 'quick_revision'
    ];
    
    $id_col_map = [
        'mcqs' => 'mcq_id',
        'notes' => 'note_id',
        'videos' => 'video_id',
        'flashcards' => 'id',
        'quick_revision' => 'revision_id'
    ];
    
    if (isset($table_map[$type])) {
        try {
            $table = $table_map[$type];
            $id_col = $id_col_map[$type];
            
            // Verify ownership via joins
            // Structure: content -> chapters -> subjects -> classes -> board_type
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
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col = ?");
                $stmt->execute([$id]);
                echo json_encode(['status' => 'success']);
            } else {
                 echo json_encode(['status' => 'error', 'message' => 'Item not found or belongs to another board']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Type']);
    }
    exit();
}

// 3. DOWNLOAD SELECTED CONTENT
if (isset($_POST['action']) && $_POST['action'] == 'download_selected') {
    $ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
    $type = $_POST['type'];
    
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No items selected']);
        exit();
    }
    
    // Check for Board Selection
    if (!isset($_SESSION['admin_selected_board'])) {
        echo json_encode(['status' => 'error', 'message' => 'Board not selected']);
        exit();
    }
    $selected_board = $_SESSION['admin_selected_board'];
    
    // Map types to tables and columns
    $config = [
        'mcqs' => [
            'table' => 'mcqs', 'id_col' => 'mcq_id', 
            'columns' => ['mcq_id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation', 'difficulty'],
            'headers' => ['ID', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer', 'Explanation', 'Difficulty']
        ],
        'notes' => [
            'table' => 'notes', 'id_col' => 'note_id',
            'columns' => ['note_id', 'title', 'file_path', 'description'],
            'headers' => ['ID', 'Title', 'File/Link', 'Description']
        ],
        'videos' => [
            'table' => 'videos', 'id_col' => 'video_id',
            'columns' => ['video_id', 'title', 'url', 'thumbnail', 'duration', 'description'],
            'headers' => ['ID', 'Title', 'Video URL', 'Thumbnail', 'Duration', 'Description']
        ],
        'flashcards' => [
            'table' => 'flashcards', 'id_col' => 'id',
            'columns' => ['id', 'question_front', 'answer_back'],
            'headers' => ['ID', 'Question (Front)', 'Answer (Back)']
        ],
        'quick_revision' => [
            'table' => 'quick_revision', 'id_col' => 'revision_id',
            'columns' => ['revision_id', 'title', 'summary', 'point_1', 'point_2', 'point_3', 'point_4', 'point_5'],
            'headers' => ['ID', 'Title', 'Summary', 'Point 1', 'Point 2', 'Point 3', 'Point 4', 'Point 5']
        ]
    ];
    
    if (!isset($config[$type])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Type']);
        exit();
    }
    
    $cfg = $config[$type];
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    // Fetch Data
    // We join to ensure board ownership is respected
    $sql = "
        SELECT t.* 
        FROM {$cfg['table']} t
        JOIN chapters ch ON t.chapter_id = ch.chapter_id
        JOIN subjects s ON ch.subject_id = s.subject_id
        JOIN classes c ON s.class_id = c.class_id
        WHERE t.{$cfg['id_col']} IN ($placeholders) AND c.board_type = ?
    ";
    
    $params = array_merge($ids, [$selected_board]);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'No valid data found']);
            exit();
        }
        
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=veeru_content_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel
        
        // Headers
        fputcsv($output, $cfg['headers']);
        
        // Rows
        foreach ($rows as $row) {
            $csvRow = [];
            foreach ($cfg['columns'] as $col) {
                $csvRow[] = $row[$col] ?? '';
            }
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit();
        
    } catch (PDOException $e) {
        die('Database Error: ' . $e->getMessage());
    }
}

// 4. DELETE SELECTED CONTENT (MULTIPLE)
if (isset($_POST['action']) && $_POST['action'] == 'delete_selected') {
    $ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
    $type = $_POST['type'];
    
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No items selected']);
        exit();
    }
    
    // Check for Board Selection
    if (!isset($_SESSION['admin_selected_board'])) {
        echo json_encode(['status' => 'error', 'message' => 'Board not selected']);
        exit();
    }
    $selected_board = $_SESSION['admin_selected_board'];
    
    $table_map = [
        'mcqs' => 'mcqs',
        'notes' => 'notes',
        'videos' => 'videos',
        'flashcards' => 'flashcards',
        'quick_revision' => 'quick_revision'
    ];
    
    $id_col_map = [
        'mcqs' => 'mcq_id',
        'notes' => 'note_id',
        'videos' => 'video_id',
        'flashcards' => 'id',
        'quick_revision' => 'revision_id'
    ];
    
    if (isset($table_map[$type])) {
        try {
            $table = $table_map[$type];
            $id_col = $id_col_map[$type];
            
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Verify ownership via joins (Security Check)
            $verify_sql = "
                SELECT t.$id_col 
                FROM $table t
                JOIN chapters ch ON t.chapter_id = ch.chapter_id
                JOIN subjects s ON ch.subject_id = s.subject_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE t.$id_col IN ($placeholders) AND c.board_type = ?
            ";
            
            // Params: IDs + Board Type
            $params = array_merge($ids, [$selected_board]);
            
            $check = $pdo->prepare($verify_sql);
            $check->execute($params);
            $valid_ids = $check->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($valid_ids)) {
                // Now delete only the valid IDs that belong to this board
                $delete_placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col IN ($delete_placeholders)");
                $stmt->execute($valid_ids);
                
                echo json_encode(['status' => 'success', 'count' => count($valid_ids)]);
            } else {
                 echo json_encode(['status' => 'error', 'message' => 'No valid items found to delete (permission denied or not found)']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Type']);
    }
    exit();
}

// ==========================================
// INITIAL DATA LOADING
// ==========================================
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

$all_subjects_query = $pdo->prepare("
    SELECT s.* FROM subjects s 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY s.subject_name
");
$all_subjects_query->execute([$selected_board]);
$all_subjects = $all_subjects_query->fetchAll();

$all_chapters_query = $pdo->prepare("
    SELECT ch.* FROM chapters ch 
    JOIN subjects s ON ch.subject_id = s.subject_id 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY ch.chapter_order
");
$all_chapters_query->execute([$selected_board]);
$all_chapters = $all_chapters_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Manager - Veeru Admin</title>
    <!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="header">
        <h1>📑 Content Manager</h1>
        
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">🔁 Switch Board</a>
        </div>

        <div class="header-right">
            <div class="admin-info">
                <div class="name">
                    <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; font-size: 13px;">
                        <?php echo htmlspecialchars($board_name); ?>
                    </span>
                    &nbsp; <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </div>
                <div class="email"><?php echo htmlspecialchars($_SESSION['admin_email']); ?></div>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users</a></li>
            <li><a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a></li>
            <li><a href="classes.php"><i class="fa-solid fa-layer-group"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a></li>
            <li><a href="chapters.php"><i class="fa-solid fa-file-lines"></i> Chapters</a></li>
            <li><a href="mcqs.php"><i class="fa-solid fa-list-check"></i> MCQs</a></li>
            <li><a href="videos.php"><i class="fa-solid fa-video"></i> Videos</a></li>
            <li><a href="notes.php"><i class="fa-solid fa-note-sticky"></i> Notes</a></li>
            <li><a href="flashcards.php"><i class="fa-solid fa-bolt"></i> Flashcards</a></li>
            <li><a href="quick_revision.php"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</a></li>
            <li><a href="content_manager.php" class="active"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        
        <!-- Filter Section -->
        <div class="card">
            <h2><i class="fa-solid fa-filter"></i> Select Content Source</h2>
            <div class="filters">
                <div class="filter-group">
                    <label>Class</label>
                    <select id="class_select">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Subject</label>
                    <select id="subject_select" disabled>
                        <option value="">Select Class First</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Chapter</label>
                    <select id="chapter_select" disabled>
                        <option value="">Select Subject First</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div id="content_area" class="card" style="display: none;">
            
            <div class="content-types-wrapper">
                <div class="content-types">
                    <button class="type-btn active" data-type="mcqs" onclick="switchType('mcqs')"><i class="fa-solid fa-list-check"></i> MCQs</button>
                    <button class="type-btn" data-type="notes" onclick="switchType('notes')"><i class="fa-solid fa-note-sticky"></i> Notes</button>
                    <button class="type-btn" data-type="videos" onclick="switchType('videos')"><i class="fa-solid fa-video"></i> Videos</button>
                    <button class="type-btn" data-type="quick_revision" onclick="switchType('quick_revision')"><i class="fa-solid fa-clock-rotate-left"></i> Quick Revision</button>
                    <button class="type-btn" data-type="flashcards" onclick="switchType('flashcards')"><i class="fa-solid fa-bolt"></i> Flashcards</button>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #edf2f7; padding-bottom: 15px; margin-bottom: 20px;">
                <h3 id="list_title" style="margin: 0; font-size: 18px; color: #4a5568;">
                    Managed Items
                </h3>
                <div class="actions">
                    <label style="margin-right: 15px; cursor: pointer; font-weight: 600; color: #4a5568;">
                        <input type="checkbox" id="select_all" onchange="toggleSelectAll()"> Select All
                    </label>
                    <button onclick="deleteSelected()" style="background: #e53e3e; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-right: 10px;">
                        <i class="fa-solid fa-trash"></i> Delete Selected
                    </button>
                    <button onclick="downloadSelected()" style="background: #48bb78; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-download"></i> Download CSV
                    </button>
                </div>
            </div>
            
            <div id="loading_indicator" class="loading" style="display: none;"><i class="fa-solid fa-spinner fa-spin"></i> Loading content...</div>
            
            <!-- Hidden form for download -->
            <form id="download_form" method="POST" action="content_manager.php" target="_blank" style="display:none;">
                <input type="hidden" name="action" value="download_selected">
                <input type="hidden" name="type" id="download_type">
                <input type="hidden" name="ids" id="download_ids">
            </form>
            
            <div id="content_list" class="content-list"></div>
            
        </div>
        
    </div>
    
    <div id="toast" class="toast"></div>

    <script>
        // Data from PHP
        const subjects = <?php echo json_encode($all_subjects); ?>;
        const chapters = <?php echo json_encode($all_chapters); ?>;
        
        // State
        let currentChapterId = null;
        let currentType = 'mcqs'; // Default
        
        // DOM Elements
        const elClass = document.getElementById('class_select');
        const elSubject = document.getElementById('subject_select');
        const elChapter = document.getElementById('chapter_select');
        const elContentArea = document.getElementById('content_area');
        const elList = document.getElementById('content_list');
        const elLoading = document.getElementById('loading_indicator');
        
        // ==========================
        // 1. FILTERS
        // ==========================
        elClass.addEventListener('change', function() {
            const classId = this.value;
            
            // Reset downstream
            elSubject.innerHTML = '<option value="">Select Subject</option>';
            elChapter.innerHTML = '<option value="">Select Subject First</option>';
            elSubject.disabled = true;
            elChapter.disabled = true;
            elContentArea.style.display = 'none';
            currentChapterId = null;
            
            if(classId) {
                elSubject.disabled = false;
                subjects.forEach(sub => {
                    if(sub.class_id == classId) {
                        const opt = document.createElement('option');
                        opt.value = sub.subject_id;
                        opt.textContent = sub.subject_name;
                        elSubject.appendChild(opt);
                    }
                });
            }
        });
        
        elSubject.addEventListener('change', function() {
            const subjectId = this.value;
            
            elChapter.innerHTML = '<option value="">Select Chapter</option>';
            elChapter.disabled = true;
            elContentArea.style.display = 'none';
            currentChapterId = null;
            
            if(subjectId) {
                elChapter.disabled = false;
                chapters.forEach(chap => {
                    if(chap.subject_id == subjectId) {
                        const opt = document.createElement('option');
                        opt.value = chap.chapter_id;
                        opt.textContent = chap.chapter_name;
                        elChapter.appendChild(opt);
                    }
                });
            }
        });
        
        elChapter.addEventListener('change', function() {
            const chapterId = this.value;
            currentChapterId = chapterId;
            
            if(chapterId) {
                elContentArea.style.display = 'block';
                // Trigger load for default type
                switchType(currentType); 
            } else {
                elContentArea.style.display = 'none';
            }
        });
        
        // ==========================
        // 2. CONTENT SWITCHING
        // ==========================
        function switchType(type) {
            currentType = type;
            
            // UI Updates
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.remove('active');
                if(btn.dataset.type === type) btn.classList.add('active');
            });
            
            const titles = {
                'mcqs': 'Managed MCQs',
                'notes': 'Managed Notes (PDFs)',
                'videos': 'Managed Videos',
                'quick_revision': 'Managed Quick Revision Points',
                'flashcards': 'Managed Flashcards'
            };
            document.getElementById('list_title').textContent = titles[type];
            
            // Uncheck select all
            document.getElementById('select_all').checked = false;
            
            loadContent();
        }
        
        // ==========================
        // 3. LOAD CONTENT (AJAX)
        // ==========================
        function loadContent() {
            if(!currentChapterId) return;
            
            elList.innerHTML = '';
            elLoading.style.display = 'block';
            
            fetch(`content_manager.php?action=get_content&chapter_id=${currentChapterId}&type=${currentType}`)
                .then(res => res.json())
                .then(res => {
                    elLoading.style.display = 'none';
                    if(res.status === 'success') {
                        renderList(res.data);
                    } else {
                        showToast('Error loading data: ' + res.message);
                    }
                })
                .catch(err => {
                    elLoading.style.display = 'none';
                    showToast('Network error');
                    console.error(err);
                });
        }
        
        function renderList(items) {
            if(items.length === 0) {
                elList.innerHTML = `<div class="empty-state">No ${currentType.replace('_',' ')} found for this chapter.</div>`;
                return;
            }
            
            elList.innerHTML = items.map(item => `
                <div class="content-item" id="item-${item.id}">
                    <div style="margin-right: 15px;">
                        <input type="checkbox" class="item-checkbox" value="${item.id}" style="transform: scale(1.2);">
                    </div>
                    <div class="item-info">
                        <div class="item-title">${escapeHtml(item.title)}</div>
                        <span class="item-subtitle">${escapeHtml(item.subtitle || '')}</span>
                    </div>
                    <button class="btn-delete" onclick="deleteItem(${item.id})"><i class="fa-solid fa-trash"></i></button>
                </div>
            `).join('');
        }
        
        function toggleSelectAll() {
            const isChecked = document.getElementById('select_all').checked;
            document.querySelectorAll('.item-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
        }
        
        function downloadSelected() {
            const selected = [];
            document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
                selected.push(cb.value);
            });
            
            if (selected.length === 0) {
                showToast('Please select at least one item');
                return;
            }
            
            // Use hidden form to submit post request
            document.getElementById('download_type').value = currentType;
            document.getElementById('download_ids').value = selected.join(',');
            document.getElementById('download_form').submit();
        }
        
        function deleteSelected() {
            const selected = [];
            document.querySelectorAll('.item-checkbox:checked').forEach(cb => {
                selected.push(cb.value);
            });
            
            if (selected.length === 0) {
                showToast('Please select at least one item to delete');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selected.length} items? This cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_selected');
            formData.append('ids', selected.join(','));
            formData.append('type', currentType);
            
            fetch('content_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    showToast(`Successfully deleted ${res.count} items.`);
                    // Reload list
                    loadContent(); 
                    document.getElementById('select_all').checked = false;
                } else {
                    showToast('Error: ' + res.message);
                }
            })
            .catch(err => {
                showToast('Network error');
                console.error(err);
            });
        }
        
        // ==========================
        // 4. DELETE CONTENT (AJAX)
        // ==========================
        window.deleteItem = function(id) {
            if(!confirm('Are you sure you want to delete this item? This cannot be undone.')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_content');
            formData.append('id', id);
            formData.append('type', currentType);
            
            fetch('content_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    // Remove from DOM with animation
                    const el = document.getElementById(`item-${id}`);
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 300);
                    showToast('Item deleted successfully');
                } else {
                    showToast('Error: ' + res.message);
                }
            })
            .catch(err => {
                showToast('Network error');
                console.error(err);
            });
        };
        
        // Utilities
        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }
        
        function escapeHtml(text) {
            if(!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
