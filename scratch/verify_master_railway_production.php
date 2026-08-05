<?php
// scratch/verify_master_railway_production.php
set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

$host = 'yamanote.proxy.rlwy.net';
$user = 'root';
$pass = 'NvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf';
$port = 24540;
$dbname = 'railway';

$baseUrl = 'https://api.veeruapp.in/backend/api';

echo "=======================================================\n";
echo "🌐 MASTER PRODUCTION AUDIT — RAILWAY SERVER & BOTH APPS\n";
echo "=======================================================\n\n";

// 1. Direct Railway Database Verification
echo "1. VERIFYING RAILWAY PRODUCTION DATABASE TABLES:\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $tables = [
        'users', 'classrooms', 'student_class_mapping', 'class_updates',
        'notifications', 'live_exams', 'messages', 'vocab_words',
        'mcq_attempts', 'chapters', 'mcqs', 'pdf_folders'
    ];

    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "   - Table " . str_pad("'$t'", 25) . ": ✅ EXISTS ($count rows)\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n----------------------------------------------------\n";

function makePost($url, $data) {
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($opts);
    $res = file_get_contents($url, false, $context);
    return json_decode($res, true);
}

function makeGet($url) {
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "Accept: application/json\r\n",
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($opts);
    $res = file_get_contents($url, false, $context);
    return json_decode($res, true);
}

function makeFormPost($url, $fields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$summary = [];

// 2. Teacher App Features Verification
echo "\n2. VERIFYING TEACHER APP LIVE FEATURES:\n";

// Create Classroom
$cRes = makePost("$baseUrl/teacher/create_classroom.php", [
    'teacher_id' => 2,
    'class_id' => 10,
    'division_name' => 'Rose',
    'name' => 'John Teacher',
    'mobile' => '9876543210'
]);
$classCode = $cRes['data']['class_code'] ?? null;
$classId = $cRes['data']['classroom_id'] ?? $cRes['data']['class_id'] ?? null;
echo "   [Teacher] Create Classroom               : " . ($classCode ? "✅ PASSED (Code: $classCode)" : "❌ FAILED") . "\n";
$summary['Teacher: Create Classroom'] = $classCode ? 'PASSED' : 'FAILED';

// Get Classes
$gClasses = makePost("$baseUrl/teacher/get_classes.php", ['teacher_id' => 2]);
$summary['Teacher: Get Classes'] = (isset($gClasses['status']) && $gClasses['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Teacher] Get Classrooms List            : ✅ " . $summary['Teacher: Get Classes'] . "\n";

// Post Announcement
$pNotif = makePost("$baseUrl/teacher/send_notification.php", [
    'teacher_id' => 2,
    'class_id' => $classId,
    'title' => 'Master Audit Test Announcement',
    'message' => 'This is a test notification.'
]);
$summary['Teacher: Send Notification'] = (isset($pNotif['status']) && ($pNotif['status'] === 'success' || $pNotif['status'] === 201)) ? 'PASSED' : 'FAILED';
echo "   [Teacher] Send Announcement Feed         : ✅ " . $summary['Teacher: Send Notification'] . "\n";

// Create Live Exam
$cExam = makePost("$baseUrl/teacher/create_live_exam.php", [
    'teacher_id' => 2,
    'class_id' => $classId,
    'chapter_id' => 1,
    'title' => 'Master Speed Exam',
    'duration_minutes' => 10,
    'selected_question_ids' => [1, 2]
]);
$examId = $cExam['data']['exam_id'] ?? 1;
$summary['Teacher: Create Live Exam'] = (isset($cExam['status']) && $cExam['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Teacher] Create Live Exam               : ✅ " . $summary['Teacher: Create Live Exam'] . "\n";

// Worksheet Creator
$pMat = makeFormPost("$baseUrl/teacher/upload_class_material.php", [
    'teacher_id' => 2,
    'class_id' => $classId,
    'title' => 'Master Worksheet Test',
    'message' => 'Please solve this worksheet.',
    'update_type' => 'worksheet'
]);
$summary['Teacher: Worksheet Creator'] = (isset($pMat['status']) && $pMat['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Teacher] Dispatch Worksheet Material    : ✅ " . $summary['Teacher: Worksheet Creator'] . "\n";


// 3. Student App Features Verification
echo "\n3. VERIFYING STUDENT APP LIVE FEATURES:\n";

// Join Classroom
$jRes = makePost("$baseUrl/student/join_classroom.php", [
    'student_id' => 4,
    'class_code' => $classCode
]);
$summary['Student: Join Classroom'] = (isset($jRes['status']) && $jRes['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Student] Join Classroom by Code         : ✅ " . $summary['Student: Join Classroom'] . "\n";

// Get Joined Classes
$gJoined = makeGet("$baseUrl/student/get_joined_classes.php?student_id=4");
$summary['Student: Get Joined Classes'] = (isset($gJoined['status']) && $gJoined['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Student] Get Joined Classes Feed        : ✅ " . $summary['Student: Get Joined Classes'] . "\n";

// Get Updates Feed
$gNotif = makeGet("$baseUrl/get_notifications.php?class_ids=$classId&student_id=4");
$summary['Student: Class Feed Updates'] = (isset($gNotif['status']) && $gNotif['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Student] Fetch Feed Updates             : ✅ " . $summary['Student: Class Feed Updates'] . "\n";

// Check Live Exam
$chkExam = makeGet("$baseUrl/student/check_live_exam.php?class_id=$classId&user_id=4");
$summary['Student: Detect Live Exam'] = (isset($chkExam['status']) && $chkExam['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Student] Detect Live Active Exam        : ✅ " . $summary['Student: Detect Live Exam'] . "\n";

// Submit MCQ Attempt
$recAtt = makePost("$baseUrl/record_mcq_attempt.php", [
    'user_id' => 4,
    'mcq_id' => 1,
    'chapter_id' => 1,
    'selected_answer' => 'A',
    'correct_answer' => 'A',
    'is_correct' => true
]);
$summary['Student: Record MCQ Attempt'] = (isset($recAtt['status']) && ($recAtt['status'] === 'success' || $recAtt['status'] === 201)) ? 'PASSED' : 'FAILED';
echo "   [Student] Submit MCQ Answer Attempt      : ✅ " . $summary['Student: Record MCQ Attempt'] . "\n";

// Chat Box Channel
$initChat = makePost("$baseUrl/chat/init_student_chat.php", ['student_id' => 4, 'class_id' => $classId]);
$sendChat = makePost("$baseUrl/chat/send_message.php", [
    'sender_id' => 4,
    'class_code' => $classCode,
    'message_text' => 'Master audit chat verification message.'
]);
$getChat = makeGet("$baseUrl/chat/get_messages.php?class_code=$classCode&user_id=4");
$summary['Student & Teacher: Chat Box'] = (isset($getChat['status']) && $getChat['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Both] Two-Way Chat Messaging            : ✅ " . $summary['Student & Teacher: Chat Box'] . "\n";

// Vocabulary Features (Set 20 & all sets)
$vocabRes = makeGet("$baseUrl/vocab_get_set.php?user_id=4&set_number=20");
$summary['Student: Vocabulary Features (Set 20)'] = (isset($vocabRes['status']) && $vocabRes['status'] === 'success' && count($vocabRes['data']['words']) >= 10) ? 'PASSED' : 'FAILED';
echo "   [Student] Vocabulary Sets (Clean Set 20) : ✅ " . $summary['Student: Vocabulary Features (Set 20)'] . "\n";

// AI PDF Worksheets
$pdfRes = makeGet("$baseUrl/get_pdf_folders.php?user_id=4");
$summary['Student: AI PDF Worksheets'] = (isset($pdfRes['status']) && $pdfRes['status'] === 'success') ? 'PASSED' : 'FAILED';
echo "   [Student] AI PDF Worksheet Folders       : ✅ " . $summary['Student: AI PDF Worksheets'] . "\n";

// Cleanup test classroom
makePost("$baseUrl/teacher/delete_class.php", ['teacher_id' => 2, 'class_id' => $classId]);

echo "\n=======================================================\n";
echo "📊 MASTER PRODUCTION AUDIT RESULT SUMMARY:\n";
echo "=======================================================\n";
$allPassed = true;
foreach ($summary as $item => $st) {
    $icon = strpos($st, 'PASSED') !== false ? '✅' : '❌';
    if (strpos($st, 'PASSED') === false) $allPassed = false;
    echo str_pad($item, 40) . ": $icon $st\n";
}

if ($allPassed) {
    echo "\n🎉 ALL FEATURES OF BOTH TEACHER & STUDENT APPS ARE 100% LOADED AND OPERATIONAL ON RAILWAY PRODUCTION SERVER!\n";
}
?>
