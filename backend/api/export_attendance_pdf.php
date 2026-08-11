<?php
/**
 * Export Printable PDF/HTML Attendance Register
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
$schoolName = isset($_GET['school_name']) ? htmlspecialchars($_GET['school_name']) : 'Veeru Educational Platform';
$teacherName = isset($_GET['teacher_name']) ? htmlspecialchars($_GET['teacher_name']) : 'Class Educator';

if ($classId <= 0) {
    echo "<h2>Error: Class ID is required.</h2>";
    exit;
}

// Fetch class name
$classStmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE class_id = ? LIMIT 1");
$classStmt->execute([$classId]);
$classObj = $classStmt->fetch(PDO::FETCH_ASSOC);
$className = $classObj ? ($classObj['class_name'] . ' ' . ($classObj['section'] ?? '')) : 'Class #' . $classId;

$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$daysInMonth = intval(date('t', strtotime($startDate)));

// Fetch sessions
$sessionsStmt = $pdo->prepare("
    SELECT session_id, session_date 
    FROM attendance_sessions 
    WHERE class_id = ? AND session_date BETWEEN ? AND ? 
    ORDER BY session_date ASC
");
$sessionsStmt->execute([$classId, $startDate, $endDate]);
$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
$sessionDates = array_column($sessions, 'session_date');

// Fetch students
$studentsStmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name, u.roll_number
    FROM student_classroom_mapping scm
    JOIN users u ON scm.student_id = u.id
    WHERE scm.class_id = ? AND scm.status = 'active'
    ORDER BY CAST(COALESCE(u.roll_number, '999999') AS UNSIGNED) ASC, u.full_name ASC
");
$studentsStmt->execute([$classId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    $fallback = $pdo->prepare("SELECT id as student_id, full_name, roll_number FROM users WHERE class_id = ? AND role = 'student' ORDER BY CAST(COALESCE(roll_number, '999999') AS UNSIGNED) ASC, full_name ASC");
    $fallback->execute([$classId]);
    $students = $fallback->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch records
$recordsMap = [];
if (!empty($sessions)) {
    $sIds = array_column($sessions, 'session_id');
    $inClause = implode(',', array_fill(0, count($sIds), '?'));
    $rStmt = $pdo->prepare("SELECT session_id, student_id, status FROM attendance_records WHERE session_id IN ($inClause)");
    $rStmt->execute($sIds);
    $sessDateMap = array_column($sessions, 'session_date', 'session_id');
    while ($row = $rStmt->fetch(PDO::FETCH_ASSOC)) {
        $stId = $row['student_id'];
        $d = $sessDateMap[$row['session_id']] ?? '';
        if ($d) {
            $recordsMap[$stId][$d] = $row['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Register - <?= htmlspecialchars($className) ?> (<?= htmlspecialchars($month) ?>)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; color: #1e293b; background-color: #fff; }
        .header-table { width: 100%; border-bottom: 3px solid #10b981; padding-bottom: 15px; margin-bottom: 20px; }
        .header-title { font-size: 24px; font-weight: bold; color: #047857; }
        .header-subtitle { font-size: 14px; color: #64748b; margin-top: 4px; }
        .badge { background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .stats-grid { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-card { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; background: #f8fafc; }
        .stat-value { font-size: 20px; font-weight: bold; color: #0f172a; }
        .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; margin-top: 2px; }
        table.register { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 30px; }
        table.register th, table.register td { border: 1px solid #cbd5e1; padding: 6px 4px; text-align: center; }
        table.register th { background: #f1f5f9; font-weight: bold; color: #334155; }
        .name-col { text-align: left !important; padding-left: 8px !important; font-weight: 600; white-space: nowrap; }
        .status-P { color: #16a34a; font-weight: bold; }
        .status-A { color: #dc2626; font-weight: bold; background-color: #fee2e2; }
        .status-L { color: #d97706; font-weight: bold; background-color: #fef3c7; }
        .status-E { color: #2563eb; font-weight: bold; background-color: #dbeafe; }
        .low-alert { border: 1px solid #fca5a5; background-color: #fef2f2; padding: 12px; border-radius: 8px; margin-bottom: 25px; }
        .signature-section { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-bottom: 1px solid #94a3b8; height: 40px; margin-bottom: 8px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer;">🖨️ Print / Save PDF</button>
    </div>

    <table class="header-table">
        <tr>
            <td>
                <div class="header-title"><?= $schoolName ?></div>
                <div class="header-subtitle">Official Student Attendance Register • <?= htmlspecialchars($className) ?></div>
            </td>
            <td style="text-align: right;">
                <span class="badge">MONTH: <?= date('F Y', strtotime($startDate)) ?></span>
                <div style="font-size: 12px; color: #64748b; margin-top: 6px;">Educator: <?= $teacherName ?></div>
            </td>
        </tr>
    </table>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($students) ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($sessions) ?></div>
            <div class="stat-label">Sessions Held</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #10b981;"><?= date('F t, Y', strtotime($startDate)) ?></div>
            <div class="stat-label">Report Period</div>
        </div>
    </div>

    <table class="register">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th class="name-col" style="width: 180px;">Student Name</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th style="width: 20px;"><?= $d ?></th>
                <?php endfor; ?>
                <th>P</th>
                <th>A</th>
                <th>L</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $lowAttList = [];
            foreach ($students as $idx => $stu): 
                $sId = intval($stu['student_id']);
                $pCount = 0; $aCount = 0; $lCount = 0; $eCount = 0;
            ?>
            <tr>
                <td><?= $stu['roll_number'] ?: ($idx + 1) ?></td>
                <td class="name-col"><?= htmlspecialchars($stu['full_name']) ?></td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                    $dateStr = sprintf('%s-%02d', $month, $d);
                    $st = $recordsMap[$sId][$dateStr] ?? '-';
                    if ($st === 'P') $pCount++;
                    elseif ($st === 'A') $aCount++;
                    elseif ($st === 'L') $lCount++;
                    elseif ($st === 'E') $eCount++;
                ?>
                    <td class="status-<?= $st ?>"><?= $st !== '-' ? $st : '' ?></td>
                <?php endfor; 
                $totSess = count($sessions);
                $attPct = $totSess > 0 ? round((($pCount + ($lCount * 0.5)) / $totSess) * 100, 1) : 0;
                if ($totSess >= 3 && $attPct < 75) {
                    $lowAttList[] = $stu['full_name'] . " ($attPct%)";
                }
                ?>
                <td style="color: #16a34a; font-weight: bold;"><?= $pCount ?></td>
                <td style="color: #dc2626; font-weight: bold;"><?= $aCount ?></td>
                <td style="color: #d97706; font-weight: bold;"><?= $lCount ?></td>
                <td style="font-weight: bold; background: <?= $attPct < 75 ? '#fee2e2' : '#f0fdf4' ?>; color: <?= $attPct < 75 ? '#b91c1c' : '#15803d' ?>;">
                    <?= $attPct ?>%
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($lowAttList)): ?>
    <div class="low-alert">
        <strong style="color: #991b1b;">⚠️ Low Attendance Alert (< 75%):</strong>
        <span style="color: #b91c1c; font-size: 12px; margin-left: 8px;"><?= implode(' • ', $lowAttList) ?></span>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div style="font-size: 12px; color: #475569;">Class Educator Signature</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div style="font-size: 12px; color: #475569;">Principal / Admin Stamp</div>
        </div>
    </div>

</body>
</html>
