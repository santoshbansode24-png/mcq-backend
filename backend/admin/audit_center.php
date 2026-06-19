<?php
/**
 * Quality Audit Center
 * Veeru
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

// Get Classes for Filter
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_id");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

// Get Subjects for Filter
$subjects_query = $pdo->prepare("
    SELECT s.* FROM subjects s 
    JOIN classes c ON s.class_id = c.class_id 
    WHERE c.board_type = ? 
    ORDER BY s.subject_name
");
$subjects_query->execute([$selected_board]);
$subjects = $subjects_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quality Audit Center - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css?v=<?php echo time(); ?>">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .type-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            margin-top: 20px;
            gap: 5px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: none;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 14px;
        }
        .tab-btn.active {
            background: white;
            color: #4f46e5;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .audit-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        .audit-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            position: relative;
            transition: transform 0.2s;
        }
        .audit-card:hover {
            transform: translateY(-2px);
        }
        .audit-card.verified {
            border-left: 6px solid #10b981;
        }
        .audit-card.flagged {
            border-left: 6px solid #ef4444;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .item-id {
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .status-badge {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-flagged { background: #fee2e2; color: #991b1b; }
        .question-text {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .options-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .option-item {
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
        }
        .option-item.correct {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
            font-weight: 600;
        }
        .explanation {
            background: #fffbef;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 15px;
            border-left: 4px solid #f59e0b;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
        }
        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .btn-action:hover {
            opacity: 0.9;
        }
        .btn-action.verify { background: #10b981; color: white; }
        .btn-action.flag { background: #f59e0b; color: white; }
        .btn-action.delete { background: #ef4444; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-icon { font-size: 40px; margin-bottom: 15px; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #1e293b; }
        .feedback-input { width: 100%; height: 80px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px; font-family: inherit; resize: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 Quality Audit Center</h1>
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
            <li><a href="content_manager.php"><i class="fa-solid fa-database"></i> Content Manager</a></li>
            <li><a href="audit_center.php" class="active"><i class="fa-solid fa-clipboard-check"></i> Audit Center</a></li>
            <li><a href="ai_settings.php"><i class="fa-solid fa-robot"></i> AI Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <div class="card">
            <h2><i class="fa-solid fa-magnifying-glass-chart"></i> Content Quality Verification</h2>
            <div class="filter-grid">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px; color:#475569;">Class</label>
                    <select id="classFilter" onchange="loadAuditContent()" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">Class <?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px; color:#475569;">Subject</label>
                    <select id="subjectFilter" onchange="loadAuditContent()" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px; color:#475569;">Status Filter</label>
                    <select id="statusFilter" onchange="loadAuditContent()" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                        <option value="pending">Pending Review</option>
                        <option value="flagged">Flagged Only</option>
                        <option value="verified">Verified Only</option>
                        <option value="all">Show All</option>
                    </select>
                </div>
            </div>
            
            <div class="type-tabs">
                <button class="tab-btn active" data-type="mcq" onclick="changeType('mcq')">MCQs</button>
                <button class="tab-btn" data-type="flashcard" onclick="changeType('flashcard')">Flashcards</button>
                <button class="tab-btn" data-type="revision" onclick="changeType('revision')">Quick Revision</button>
            </div>
        </div>
        
        <div id="auditList" class="audit-list">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <h3>Start Auditing</h3>
                <p>Select a class to view questions and start verification.</p>
            </div>
        </div>
    </div>
    
    <!-- Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Flag Content</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 10px;">What is incorrect about this item?</p>
            <textarea id="feedbackText" class="feedback-input" placeholder="e.g. Option B is wrong, typo in question..."></textarea>
            <div style="display: flex; gap: 10px;">
                <button class="btn-action flag" style="flex: 1; justify-content: center;" onclick="submitFlag()">Flag Now</button>
                <button class="btn-action" style="background: #cbd5e1; color: #1e293b; flex: 1; justify-content: center;" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentType = 'mcq';
        let activeItem = null;
        const subjects = <?php echo json_encode($subjects); ?>;

        document.getElementById('classFilter').addEventListener('change', function() {
            const classId = this.value;
            const subjectFilter = document.getElementById('subjectFilter');
            subjectFilter.innerHTML = '<option value="">All Subjects</option>';
            
            subjects.forEach(s => {
                if (s.class_id == classId) {
                    const opt = document.createElement('option');
                    opt.value = s.subject_id;
                    opt.textContent = s.subject_name;
                    subjectFilter.appendChild(opt);
                }
            });
        });

        function changeType(type) {
            currentType = type;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`[data-type="${type}"]`).classList.add('active');
            loadAuditContent();
        }

        async function loadAuditContent() {
            const classId = document.getElementById('classFilter').value;
            const subjectId = document.getElementById('subjectFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            if (!classId) return;

            const auditList = document.getElementById('auditList');
            auditList.innerHTML = '<div class="empty-state"><h3><i class="fa-solid fa-spinner fa-spin"></i> Loading data...</h3></div>';

            try {
                const response = await fetch(`api/audit_actions.php?action=list&type=${currentType}&class_id=${classId}&subject_id=${subjectId}&status=${status}`);
                const result = await response.json();
                
                if (result.status === 'success' && result.data.length > 0) {
                    renderAuditList(result.data);
                } else {
                    auditList.innerHTML = '<div class="empty-state"><div class="empty-icon">✅</div><h3>All caught up!</h3><p>No items found for this filter.</p></div>';
                }
            } catch (error) {
                auditList.innerHTML = '<div class="empty-state"><div class="empty-icon">❌</div><h3>Connection Error</h3><p>Could not fetch audit data.</p></div>';
            }
        }

        function renderAuditList(data) {
            const auditList = document.getElementById('auditList');
            auditList.innerHTML = '';
            
            data.forEach(item => {
                const card = document.createElement('div');
                card.className = `audit-card ${item.status === 'verified' ? 'verified' : (item.status === 'flagged' ? 'flagged' : '')}`;
                card.id = `item-${item.id}`;
                
                let contentHtml = '';
                if (currentType === 'mcq') {
                    contentHtml = `
                        <div class="card-header">
                            <span class="item-id">MCQ #${item.id}</span>
                            ${item.status ? `<span class="status-badge status-${item.status}">${item.status}</span>` : ''}
                        </div>
                        <div class="question-text">${item.question}</div>
                        <div class="options-list">
                            <div class="option-item ${item.correct_answer === 'a' ? 'correct' : ''}">A: ${item.option_a}</div>
                            <div class="option-item ${item.correct_answer === 'b' ? 'correct' : ''}">B: ${item.option_b}</div>
                            <div class="option-item ${item.correct_answer === 'c' ? 'correct' : ''}">C: ${item.option_c}</div>
                            <div class="option-item ${item.correct_answer === 'd' ? 'correct' : ''}">D: ${item.option_d}</div>
                        </div>
                        ${item.explanation ? `<div class="explanation"><strong>Explanation:</strong> ${item.explanation}</div>` : ''}
                    `;
                } else if (currentType === 'flashcard') {
                    contentHtml = `
                        <div class="card-header">
                            <span class="item-id">FLASHCARD #${item.id}</span>
                            ${item.status ? `<span class="status-badge status-${item.status}">${item.status}</span>` : ''}
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div style="padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #cbd5e1;">
                                <label style="font-size: 10px; font-weight: 800; color: #4f46e5; display: block; margin-bottom: 5px;">FRONT (QUESTION)</label>
                                <div style="font-weight: 700;">${item.question_front}</div>
                            </div>
                            <div style="padding: 15px; background: #ecfdf5; border-radius: 8px; border: 1px solid #a7f3d0;">
                                <label style="font-size: 10px; font-weight: 800; color: #059669; display: block; margin-bottom: 5px;">BACK (ANSWER)</label>
                                <div style="font-weight: 700;">${item.answer_back}</div>
                            </div>
                        </div>
                    `;
                } else {
                    // Quick Revision
                    contentHtml = `
                        <div class="card-header">
                            <span class="item-id">REVISION #${item.id}</span>
                            ${item.status ? `<span class="status-badge status-${item.status}">${item.status}</span>` : ''}
                        </div>
                        <div class="question-text">${item.title}</div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border: 1px solid #cbd5e1;">
                            ${item.summary}
                        </div>
                    `;
                }

                if (item.admin_feedback) {
                    contentHtml += `<div style="background: #fef2f2; color: #b91c1c; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px; border: 1px solid #fee2e2;">🚩 <strong>Feedback:</strong> ${item.admin_feedback}</div>`;
                }

                card.innerHTML = contentHtml + `
                    <div class="card-actions">
                        <button class="btn-action verify" onclick="auditAction('${item.id}', 'verify')"><i class="fa-solid fa-check"></i> Verify</button>
                        <button class="btn-action flag" onclick="openFlagModal('${item.id}')"><i class="fa-solid fa-flag"></i> Flag</button>
                        <button class="btn-action delete" onclick="auditAction('${item.id}', 'delete')"><i class="fa-solid fa-trash"></i> Delete</button>
                    </div>
                `;
                
                auditList.appendChild(card);
            });
        }

        async function auditAction(id, action) {
            if (action === 'delete' && !confirm('Are you absolutely sure you want to delete this?')) return;
            
            try {
                const response = await fetch('api/audit_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, id, type: currentType })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    if (action === 'delete') {
                        document.getElementById(`item-${id}`).style.opacity = '0';
                        setTimeout(() => document.getElementById(`item-${id}`).remove(), 300);
                    } else {
                        loadAuditContent(); // Refresh
                    }
                }
            } catch (error) {
                alert('Action failed');
            }
        }

        function openFlagModal(id) {
            activeItem = id;
            document.getElementById('feedbackModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('feedbackModal').style.display = 'none';
            document.getElementById('feedbackText').value = '';
        }

        async function submitFlag() {
            const feedback = document.getElementById('feedbackText').value;
            if (!feedback) return alert('Please enter feedback');
            
            try {
                const response = await fetch('api/audit_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'flag', id: activeItem, type: currentType, feedback })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    closeModal();
                    loadAuditContent();
                }
            } catch (error) {
                alert('Flag failed');
            }
        }
    </script>
</body>
</html>
