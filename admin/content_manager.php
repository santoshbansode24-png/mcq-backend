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
        if ($type == 'mcqs') {
            $stmt = $pdo->prepare("SELECT mcq_id as id, question as title, difficulty as subtitle FROM mcqs WHERE chapter_id = ? ORDER BY mcq_id DESC");
            $stmt->execute([$chapter_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'notes') {
            $stmt = $pdo->prepare("SELECT note_id as id, title, file_path as subtitle FROM notes WHERE chapter_id = ? ORDER BY note_id DESC");
            $stmt->execute([$chapter_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'videos') {
            $stmt = $pdo->prepare("SELECT video_id as id, title, url as subtitle FROM videos WHERE chapter_id = ? ORDER BY video_id DESC");
            $stmt->execute([$chapter_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'flashcards') {
            $stmt = $pdo->prepare("SELECT id as id, question_front as title, answer_back as subtitle FROM flashcards WHERE chapter_id = ? ORDER BY id DESC");
            $stmt->execute([$chapter_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($type == 'quick_revision') {
            $stmt = $pdo->prepare("SELECT revision_id as id, title, LEFT(summary, 50) as subtitle FROM quick_revision WHERE chapter_id = ? ORDER BY revision_id DESC");
            $stmt->execute([$chapter_id]);
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
            
            $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_col = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['status' => 'success']);
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
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id")->fetchAll();
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$all_chapters = $pdo->query("SELECT * FROM chapters ORDER BY chapter_order")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Manager - Veeru Admin</title>
    <style>
        /* Reusing consistent styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #333; }
        
        /* Header & Nav */
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto; white-space: nowrap; }
        .nav ul { list-style: none; display: flex; gap: 5px; }
        .nav li a { display: block; padding: 18px 25px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .nav li a:hover, .nav li a.active { color: #667eea; border-bottom-color: #667eea; background: #f8f9fa; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        h2 { margin-bottom: 20px; color: #2d3748; font-weight: 600; }
        
        /* Filters */
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; font-size: 14px; }
        .filter-group select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: white; font-size: 15px; color: #2d3748; transition: border-color 0.2s; }
        .filter-group select:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        /* Content Types */
        .content-types-wrapper { overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; }
        .content-types { display: flex; gap: 15px; min-width: max-content; }
        .type-btn { 
            padding: 12px 25px; 
            border: 1px solid #e2e8f0; 
            background: white; 
            border-radius: 50px; 
            cursor: pointer; 
            font-size: 15px; 
            font-weight: 600; 
            color: #4a5568; 
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .type-btn:hover { background: #f7fafc; transform: translateY(-1px); }
        .type-btn.active { background: #667eea; color: white; border-color: #667eea; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
        .type-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        /* Content List */
        .content-list { display: flex; flex-direction: column; gap: 15px; }
        .content-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 20px; 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 10px; 
            transition: all 0.2s; 
        }
        .content-item:hover { transform: translateX(5px); border-color: #cbd5e0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        
        .item-info { flex: 1; margin-right: 20px; }
        .item-title { font-size: 16px; font-weight: 600; color: #2d3748; margin-bottom: 5px; }
        .item-subtitle { font-size: 13px; color: #718096; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px; display: block; }
        
        .btn-delete { 
            background: #fff5f5; 
            color: #c53030; 
            border: 1px solid #feb2b2; 
            padding: 8px 16px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 13px; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .btn-delete:hover { background: #c53030; color: white; border-color: #c53030; }
        
        .empty-state { text-align: center; padding: 50px; color: #a0aec0; }
        .loading { text-align: center; padding: 40px; color: #667eea; font-weight: 600; }
        
        /* Toast */
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 15px 25px; background: #2d3748; color: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transform: translateY(100px); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000; }
        .toast.show { transform: translateY(0); }
    </style>
</head>
<body>

    <div class="header">
        <h1>📑 Content Manager</h1>
        <a href="logout.php" style="color: white; text-decoration: none; font-weight: 500;">Logout</a>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="content_manager.php" class="active">Content Manager</a></li>
        </ul>
    </nav>
    
    <div class="container">
        
        <!-- Filter Section -->
        <div class="card">
            <h2>Select Content Source</h2>
            <div class="filters">
                <div class="filter-group">
                    <label>Class</label>
                    <select id="class_select">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
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
        
        <!-- Content Area (Hidden initially) -->
        <div id="content_area" class="card" style="display: none;">
            
            <div class="content-types-wrapper">
                <div class="content-types">
                    <button class="type-btn" data-type="mcqs" onclick="switchType('mcqs')">📝 MCQs</button>
                    <button class="type-btn" data-type="notes" onclick="switchType('notes')">📄 Notes</button>
                    <button class="type-btn" data-type="videos" onclick="switchType('videos')">🎥 Videos</button>
                    <button class="type-btn" data-type="quick_revision" onclick="switchType('quick_revision')">⚡ Quick Revision</button>
                    <button class="type-btn" data-type="flashcards" onclick="switchType('flashcards')">🎴 Flashcards</button>
                </div>
            </div>
            
            <h3 id="list_title" style="margin-bottom: 20px; font-size: 18px; color: #4a5568; border-bottom: 2px solid #edf2f7; padding-bottom: 10px;">
                Managed Items
            </h3>
            
            <div id="loading_indicator" class="loading" style="display: none;">Loading content...</div>
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
                    <div class="item-info">
                        <div class="item-title">${escapeHtml(item.title)}</div>
                        <span class="item-subtitle">${escapeHtml(item.subtitle || '')}</span>
                    </div>
                    <button class="btn-delete" onclick="deleteItem(${item.id})">Delete</button>
                </div>
            `).join('');
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
