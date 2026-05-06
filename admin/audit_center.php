<?php
/**
 * Content Audit Center
 * Veeru
 * 
 * Purpose: Dedicated board/class wise verification of MCQs, Flashcards, and Quick Revision.
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
    <title>Audit Center - Veeru Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #059669;
            --bg: #F9FAFB;
            --card-bg: rgba(255, 255, 255, 0.9);
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f2f5;
            color: var(--text-main);
            line-height: 1.6;
        }

        .header { 
            background: linear-gradient(135deg, #064E3B 0%, #10B981 100%); 
            color: white; 
            padding: 20px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto; }
        .nav ul { list-style: none; display: flex; gap: 5px; min-width: max-content; }
        .nav li a { display: block; padding: 18px 20px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .nav li a:hover, .nav li a.active { color: var(--primary); border-bottom-color: var(--primary); }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* Filter Section */
        .audit-header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .select-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-main);
            background: #F9FAFB;
            cursor: pointer;
            transition: all 0.2s;
        }

        .select-input:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
        }

        /* Content Tabs */
        .type-tabs {
            display: flex;
            background: #F3F4F6;
            padding: 5px;
            border-radius: 12px;
            margin-top: 25px;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 14px;
        }

        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        /* Audit Cards */
        .audit-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .audit-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid #E5E7EB;
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }

        .audit-card:hover {
            transform: translateY(-2px);
        }

        .audit-card.verified {
            border-left: 8px solid #10B981;
        }

        .audit-card.flagged {
            border-left: 8px solid #EF4444;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .item-id {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            background: #F3F4F6;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .question-text {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 15px;
        }

        .options-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .option-item {
            padding: 12px 15px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }

        .option-item.correct {
            background: #ECFDF5;
            border-color: #10B981;
            color: #065F46;
            font-weight: 700;
        }

        .explanation {
            background: #FFFBEB;
            padding: 15px;
            border-radius: 12px;
            font-size: 13px;
            color: #92400E;
            margin-bottom: 20px;
            border-left: 4px solid #F59E0B;
        }

        /* Action Buttons */
        .card-actions {
            display: flex;
            gap: 12px;
            border-top: 1px solid #F3F4F6;
            padding-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-verify { background: #10B981; color: white; }
        .btn-verify:hover { background: #059669; }

        .btn-flag { background: #F59E0B; color: white; }
        .btn-flag:hover { background: #D97706; }

        .btn-delete { background: #EF4444; color: white; }
        .btn-delete:hover { background: #DC2626; }

        .btn-edit { background: #3B82F6; color: white; }
        .btn-edit:hover { background: #2563EB; }

        /* Status Badges */
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-verified { background: #D1FAE5; color: #065F46; }
        .status-flagged { background: #FEE2E2; color: #991B1B; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: var(--text-muted);
        }

        .empty-icon { font-size: 60px; margin-bottom: 20px; }

        /* Feedback Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
        }

        .modal-title { font-size: 20px; font-weight: 800; margin-bottom: 15px; }
        .feedback-input { width: 100%; height: 100px; padding: 15px; border: 2px solid #E5E7EB; border-radius: 12px; margin-bottom: 20px; font-family: inherit; }

    </style>
</head>
<body>
    <div class="header">
        <h1>🛠️ Quality Audit Center</h1>
        <div class="header-right">
            <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 6px; font-size: 14px; font-weight: 700;">
                <?php echo htmlspecialchars($board_name); ?>
            </span>
            <a href="logout.php" style="color: white; margin-left: 20px; font-weight: 700; text-decoration: none;">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="audit_center.php" class="active">Audit Center</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="audit-header">
            <h2>Content Verification</h2>
            <p style="color: var(--text-muted); font-size: 14px;">Select filters to audit specific class content.</p>

            <div class="filter-grid">
                <div class="filter-group">
                    <label>Class</label>
                    <select id="classFilter" class="select-input" onchange="loadAuditContent()">
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">Class <?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Subject</label>
                    <select id="subjectFilter" class="select-input" onchange="loadAuditContent()">
                        <option value="">All Subjects</option>
                        <!-- Populated via JS -->
                    </select>
                </div>

                <div class="filter-group">
                    <label>Filter by Status</label>
                    <select id="statusFilter" class="select-input" onchange="loadAuditContent()">
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
            <!-- Content loaded via AJAX -->
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>Start Auditing</h3>
                <p>Select a class to view questions and start verification.</p>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Flag Content</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 10px;">What is incorrect about this item?</p>
            <textarea id="feedbackText" class="feedback-input" placeholder="e.g. Option B is wrong, typo in question..."></textarea>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-flag" style="flex: 1; justify-content: center;" onclick="submitFlag()">Flag Now</button>
                <button class="btn" style="background: #E5E7EB; color: #374151;" onclick="closeModal()">Cancel</button>
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
            auditList.innerHTML = '<div class="empty-state"><ActivityIndicator size="large" color="#10B981" /><h3>Loading data...</h3></div>';

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
                            ${item.status !== 'pending' ? `<span class="status-badge status-${item.status}">${item.status}</span>` : ''}
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
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div style="padding: 20px; background: #EEF2FF; border-radius: 15px; border: 1px solid #C7D2FE;">
                                <label style="font-size: 10px; font-weight: 800; color: #4F46E5; display: block; margin-bottom: 10px;">FRONT (QUESTION)</label>
                                <div style="font-weight: 700;">${item.front_text}</div>
                            </div>
                            <div style="padding: 20px; background: #ECFDF5; border-radius: 15px; border: 1px solid #A7F3D0;">
                                <label style="font-size: 10px; font-weight: 800; color: #059669; display: block; margin-bottom: 10px;">BACK (ANSWER)</label>
                                <div style="font-weight: 700;">${item.back_text}</div>
                            </div>
                        </div>
                    `;
                } else {
                    // Quick Revision
                    contentHtml = `
                        <div class="card-header">
                            <span class="item-id">REVISION #${item.id}</span>
                        </div>
                        <div class="question-text">${item.title}</div>
                        <div style="background: #F3F4F6; padding: 15px; border-radius: 10px; margin-bottom: 15px; font-size: 14px;">
                            ${item.summary}
                        </div>
                    `;
                }

                if (item.admin_feedback) {
                    contentHtml += `<div style="background: #FEF2F2; color: #B91C1C; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px;">🚩 <strong>Feedback:</strong> ${item.admin_feedback}</div>`;
                }

                card.innerHTML = contentHtml + `
                    <div class="card-actions">
                        <button class="btn btn-verify" onclick="auditAction('${item.id}', 'verify')">Verify ✅</button>
                        <button class="btn btn-flag" onclick="openFlagModal('${item.id}')">Flag 🚩</button>
                        <button class="btn btn-delete" onclick="auditAction('${item.id}', 'delete')">Delete 🗑️</button>
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
                        loadAuditContent(); // Refresh to update status
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
