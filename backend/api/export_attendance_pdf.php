<?php
/**
 * Export Monthly Attendance Register PDF Endpoint
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m'); // YYYY-MM
$schoolName = isset($_GET['school_name']) ? htmlspecialchars(trim($_GET['school_name'])) : 'Veeru Educational Institute';
$teacherName = isset($_GET['teacher_name']) ? htmlspecialchars(trim($_GET['teacher_name'])) : 'Class Educator';

if ($classId <= 0) {
    die("Invalid Class ID");
}

$startDate = $month . '-01';
$endDate = date("Y-m-t", strtotime($startDate));
$daysInMonth = date("t", strtotime($startDate));

// Fetch class info
$classStmt = $pdo->prepare("SELECT class_name, division_name, class_code FROM teacher_classes WHERE class_id = ? LIMIT 1");
$classStmt->execute([$classId]);
$classInfo = $classStmt->fetch(PDO::FETCH_ASSOC);
$className = $classInfo ? ($classInfo['class_name'] . ' - ' . ($classInfo['division_name'] ?: 'A')) : 'Class #' . $classId;

// Fetch sessions
$sessionsStmt = $pdo->prepare("
    SELECT session_id, session_date 
    FROM attendance_sessions 
    WHERE class_id = ? AND session_date BETWEEN ? AND ? 
    ORDER BY session_date ASC
");
$sessionsStmt->execute([$classId, $startDate, $endDate]);
$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students
$studentsStmt = $pdo->prepare("
    SELECT 
        COALESCE(u.id, u.user_id) as student_id,
        COALESCE(u.full_name, u.name) as full_name,
        u.roll_number
    FROM student_classroom_mapping scm
    JOIN users u ON scm.student_id = COALESCE(u.id, u.user_id)
    WHERE scm.class_id = ? AND scm.status = 'active'
    ORDER BY CAST(COALESCE(u.roll_number, '999999') AS UNSIGNED) ASC, COALESCE(u.full_name, u.name) ASC
");
$studentsStmt->execute([$classId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    $fallback = $pdo->prepare("
        SELECT 
            COALESCE(id, user_id) as student_id,
            COALESCE(full_name, name) as full_name,
            roll_number
        FROM users
        WHERE class_id = ? AND (user_type = 'student' OR role = 'student')
        ORDER BY CAST(COALESCE(roll_number, '999999') AS UNSIGNED) ASC, COALESCE(full_name, name) ASC
    ");
    $fallback->execute([$classId]);
    $students = $fallback->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch records map
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
    <title>Attendance Register - <?php echo $className; ?> (<?php echo date('F Y', strtotime($startDate)); ?>)</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; padding: 20px; }
        .page { background: #fff; border-radius: 12px; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #10b981; padding-bottom: 16px; margin-bottom: 20px; }
        .school-name { font-size: 22px; font-weight: 800; color: #047857; }
        .report-title { font-size: 14px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-top: 4px; }
        .meta-box { text-align: right; font-size: 13px; color: #475569; }
        .meta-box strong { color: #0f172a; }
        .action-bar { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .print-btn { background: #10b981; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 25px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 4px; text-align: center; }
        th { background: #f1f5f9; font-weight: 700; color: #334155; }
        th.stu-col, td.stu-col { text-align: left; padding-left: 8px; font-weight: 600; }
        .p-stat { color: #059669; font-weight: 700; }
        .a-stat { color: #dc2626; font-weight: 700; background: #fee2e2; }
        .l-stat { color: #d97706; font-weight: 700; }
        .e-stat { color: #2563eb; font-weight: 700; }
        .pct-warn { background: #fef2f2; color: #b91c1c; font-weight: 800; }
        .footer-sig { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px dashed #cbd5e1; font-size: 12px; font-weight: 600; color: #475569; }
        @media print {
            body { background: #fff; padding: 0; }
            .page { border: none; box-shadow: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="header">
        <div>
            <div class="school-name"><?php echo $schoolName; ?></div>
            <div class="report-title">Monthly Student Attendance Register • <?php echo date('F Y', strtotime($startDate)); ?></div>
        </div>
        <div class="meta-box">
            <div>Class: <strong><?php echo $className; ?></strong></div>
            <div>Teacher: <strong><?php echo $teacherName; ?></strong></div>
            <div>Days Active: <strong><?php echo count($sessions); ?> Days</strong></div>
        </div>
    </div>

    <div class="action-bar">
        <button class="print-btn" onclick="window.print()">📥 Print / Save PDF</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th class="stu-col" style="min-width: 140px;">Student Name</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th style="width: 22px;"><?php echo $d; ?></th>
                <?php endfor; ?>
                <th>P</th>
                <th>A</th>
                <th>L</th>
                <th>Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sNum = 1;
            foreach ($students as $stu): 
                $stId = $stu['student_id'];
                $pCount = 0; $aCount = 0; $lCount = 0; $eCount = 0;
            ?>
                <tr>
                    <td><?php echo $stu['roll_number'] ?: $sNum; ?></td>
                    <td class="stu-col"><?php echo htmlspecialchars($stu['full_name']); ?></td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $dayStr = sprintf('%s-%02d', $month, $d);
                        $st = $recordsMap[$stId][$dayStr] ?? '-';
                        $classStyle = '';
                        if ($st === 'P') { $pCount++; $classStyle = 'p-stat'; }
                        elseif ($st === 'A') { $aCount++; $classStyle = 'a-stat'; }
                        elseif ($st === 'L') { $lCount++; $classStyle = 'l-stat'; }
                        elseif ($st === 'E') { $eCount++; $classStyle = 'e-stat'; }
                    ?>
                        <td class="<?php echo $classStyle; ?>"><?php echo $st; ?></td>
                    <?php endfor; ?>
                    <?php 
                        $totalMarked = $pCount + $aCount + $lCount + $eCount;
                        $pct = $totalMarked > 0 ? Math.round((($pCount + $lCount * 0.5) / $totalMarked) * 100) : 100;
                    ?>
                    <td style="font-weight: 700; color: #059669;"><?php echo $pCount; ?></td>
                    <td style="font-weight: 700; color: #dc2626;"><?php echo $aCount; ?></td>
                    <td style="font-weight: 700; color: #d97706;"><?php echo $lCount; ?></td>
                    <td class="<?php echo $pct < 75 ? 'pct-warn' : ''; ?>"><?php echo $pct; ?>%</td>
                </tr>
            <?php 
                $sNum++;
            endforeach; 
            ?>
        </tbody>
    </table>

    <div class="footer-sig">
        <div>Class Teacher Signature: _______________________</div>
        <div>Principal / Admin Stamp: _______________________</div>
    </div>
</div>

</body>
</html>
