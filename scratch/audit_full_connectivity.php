<?php
// scratch/audit_full_connectivity.php
set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

$baseUrl = 'https://api.veeruapp.in/backend/api';
$testTeacherId = 2; // John Teacher (Valid teacher user_type)
$testStudentId = 4; // Test Student (Valid student user_type)

echo "=======================================================\n";
echo "🚀 VEERU APP CONNECTIVITY & FEATURE INTEGRATION AUDIT\n";
echo "=======================================================\n\n";

function makePostRequest($url, $data) {
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

function makeGetRequest($url) {
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

$report = [];

// ----------------------------------------------------
// 1. CLASSROOM CREATION & STUDENT JOIN CONNECTIVITY
// ----------------------------------------------------
echo "1. Testing Classroom Creation (Teacher App) -> Join (Student App)... \n";

$createClassRes = makePostRequest("$baseUrl/teacher/create_classroom.php", [
    'teacher_id' => $testTeacherId,
    'class_id' => 10,
    'division_name' => 'Section-A',
    'name' => 'John Teacher',
    'mobile' => '9876543210'
]);

if (isset($createClassRes['status']) && $createClassRes['status'] === 'success') {
    $classCode = $createClassRes['data']['class_code'];
    $classroomId = $createClassRes['data']['classroom_id'] ?? $createClassRes['data']['class_id'];
    echo "   ✅ Teacher App created classroom! Code: '$classCode' (Classroom ID: $classroomId)\n";

    // Student Joins
    $joinRes = makePostRequest("$baseUrl/student/join_classroom.php", [
        'student_id' => $testStudentId,
        'class_code' => $classCode
    ]);

    if (isset($joinRes['status']) && $joinRes['status'] === 'success') {
        echo "   ✅ Student App joined classroom code '$classCode' successfully!\n";

        // Verify Student Joined Classes List
        $joinedListRes = makeGetRequest("$baseUrl/student/get_joined_classes.php?student_id=$testStudentId");
        if (isset($joinedListRes['status']) && $joinedListRes['status'] === 'success') {
            echo "   ✅ Student App fetched joined classes! Total joined classrooms: " . count($joinedListRes['data']) . "\n";
            $report['1. Classroom Join Flow'] = "PASSED";
        } else {
            echo "   ❌ Student get_joined_classes failed: " . json_encode($joinedListRes) . "\n";
            $report['1. Classroom Join Flow'] = "FAILED (Get Joined Classes)";
        }
    } else {
        echo "   ❌ Student join_classroom failed: " . json_encode($joinRes) . "\n";
        $report['1. Classroom Join Flow'] = "FAILED (Join Classroom)";
    }
} else {
    echo "   ❌ Teacher create_classroom failed: " . json_encode($createClassRes) . "\n";
    $report['1. Classroom Join Flow'] = "FAILED (Create Classroom)";
}

echo "\n----------------------------------------------------\n";

// ----------------------------------------------------
// 2. SEND NOTIFICATION / ANNOUNCEMENT CONNECTIVITY
// ----------------------------------------------------
echo "2. Testing Send Notification (Teacher App) -> Fetch Updates (Student App)... \n";

if (isset($classroomId)) {
    $notifTitle = "Important Announcement " . rand(1000, 9999);
    $notifMsg = "Tomorrow morning at 9:00 AM there will be a special revision test.";

    $sendNotifRes = makePostRequest("$baseUrl/teacher/send_notification.php", [
        'teacher_id' => $testTeacherId,
        'class_id' => $classroomId,
        'title' => $notifTitle,
        'message' => $notifMsg
    ]);

    if (isset($sendNotifRes['status']) && ($sendNotifRes['status'] === 'success' || $sendNotifRes['status'] === 201)) {
        echo "   ✅ Teacher App sent notification successfully! Notification ID: " . ($sendNotifRes['data']['notification_id'] ?? 'N/A') . "\n";

        // Student fetches notifications
        $getNotifRes = makeGetRequest("$baseUrl/get_notifications.php?class_ids=$classroomId&student_id=$testStudentId");
        if (isset($getNotifRes['status']) && $getNotifRes['status'] === 'success') {
            echo "   ✅ Student App fetched class updates & announcements! Total items: " . count($getNotifRes['data']) . "\n";
            $report['2. Send Notification / Updates'] = "PASSED";
        } else {
            echo "   ❌ Student get_notifications failed: " . json_encode($getNotifRes) . "\n";
            $report['2. Send Notification / Updates'] = "FAILED (Fetch Updates)";
        }
    } else {
        echo "   ❌ Teacher send_notification failed: " . json_encode($sendNotifRes) . "\n";
        $report['2. Send Notification / Updates'] = "FAILED (Send Notification)";
    }
} else {
    $report['2. Send Notification / Updates'] = "SKIPPED";
}

echo "\n----------------------------------------------------\n";

// ----------------------------------------------------
// 3. LIVE EXAM CONNECTIVITY
// ----------------------------------------------------
echo "3. Testing Live Exam Creation (Teacher App) -> Check & Attempt (Student App)... \n";

if (isset($classroomId)) {
    $examTitle = "Mathematics Speed Test " . rand(100, 999);
    $createExamRes = makePostRequest("$baseUrl/teacher/create_live_exam.php", [
        'teacher_id' => $testTeacherId,
        'class_id' => $classroomId,
        'chapter_id' => 1,
        'title' => $examTitle,
        'duration_minutes' => 15,
        'selected_question_ids' => [1, 2, 3]
    ]);

    if (isset($createExamRes['status']) && $createExamRes['status'] === 'success') {
        $examId = $createExamRes['data']['exam_id'] ?? $createExamRes['data']['id'] ?? 1;
        echo "   ✅ Teacher App created live exam! Exam ID: $examId, Title: '$examTitle'\n";

        // Student checks active live exam for classroom
        $checkExamRes = makeGetRequest("$baseUrl/student/check_live_exam.php?class_id=$classroomId&user_id=$testStudentId");
        if (isset($checkExamRes['status']) && $checkExamRes['status'] === 'success') {
            echo "   ✅ Student App detected active live exam session!\n";

            // Student records question attempt
            $attemptRes = makePostRequest("$baseUrl/record_mcq_attempt.php", [
                'user_id' => $testStudentId,
                'mcq_id' => 1,
                'chapter_id' => 1,
                'selected_answer' => 'A',
                'correct_answer' => 'A',
                'is_correct' => true
            ]);

            if (isset($attemptRes['status']) && ($attemptRes['status'] === 'success' || $attemptRes['status'] === 201)) {
                echo "   ✅ Student App recorded live MCQ attempt successfully!\n";
                $report['3. Live Exam Feature'] = "PASSED";
            } else {
                echo "   ❌ Student record_mcq_attempt failed: " . json_encode($attemptRes) . "\n";
                $report['3. Live Exam Feature'] = "FAILED (Record Attempt)";
            }
        } else {
            echo "   ❌ Student check_live_exam failed: " . json_encode($checkExamRes) . "\n";
            $report['3. Live Exam Feature'] = "FAILED (Check Exam)";
        }
    } else {
        echo "   ❌ Teacher create_live_exam failed: " . json_encode($createExamRes) . "\n";
        $report['3. Live Exam Feature'] = "FAILED (Create Exam)";
    }
} else {
    $report['3. Live Exam Feature'] = "SKIPPED";
}

echo "\n----------------------------------------------------\n";

// ----------------------------------------------------
// 4. CHAT BOX CONNECTIVITY
// ----------------------------------------------------
echo "4. Testing Chat Box (Teacher App <-> Student App Messaging)... \n";

if (isset($classCode) && isset($classroomId)) {
    // Student initializes chat
    $initChatRes = makePostRequest("$baseUrl/chat/init_student_chat.php", [
        'student_id' => $testStudentId,
        'class_id' => $classroomId
    ]);

    if (isset($initChatRes['status']) && $initChatRes['status'] === 'success') {
        echo "   ✅ Student App initialized teacher-student chat channel!\n";

        // Student sends message to Teacher
        $sendMsgRes = makePostRequest("$baseUrl/chat/send_message.php", [
            'sender_id' => $testStudentId,
            'sender_type' => 'student',
            'class_code' => $classCode,
            'message' => 'Good morning Teacher! Need help with Chapter 3 problem.',
            'message_text' => 'Good morning Teacher! Need help with Chapter 3 problem.'
        ]);

        if (isset($sendMsgRes['status']) && $sendMsgRes['status'] === 'success') {
            echo "   ✅ Student App sent chat message to Teacher!\n";

            // Teacher responds
            $teacherReplyRes = makePostRequest("$baseUrl/chat/send_message.php", [
                'sender_id' => $testTeacherId,
                'sender_type' => 'teacher',
                'class_code' => $classCode,
                'with_user_id' => $testStudentId,
                'message' => 'Hello! Sure, let us solve Chapter 3 problem together.',
                'message_text' => 'Hello! Sure, let us solve Chapter 3 problem together.'
            ]);

            if (isset($teacherReplyRes['status']) && $teacherReplyRes['status'] === 'success') {
                echo "   ✅ Teacher App sent reply message to Student!\n";

                // Fetch conversation history
                $getMsgRes = makeGetRequest("$baseUrl/chat/get_messages.php?class_code=$classCode&user_id=$testStudentId");
                if (isset($getMsgRes['status']) && $getMsgRes['status'] === 'success') {
                    echo "   ✅ Chat Box conversation history fetched cleanly! Total messages: " . count($getMsgRes['data']) . "\n";
                    $report['4. Chat Box Feature'] = "PASSED";
                } else {
                    echo "   ❌ Fetch chat messages failed: " . json_encode($getMsgRes) . "\n";
                    $report['4. Chat Box Feature'] = "FAILED (Get Messages)";
                }
            } else {
                echo "   ❌ Teacher send_message failed: " . json_encode($teacherReplyRes) . "\n";
                $report['4. Chat Box Feature'] = "FAILED (Teacher Reply)";
            }
        } else {
            echo "   ❌ Student send_message failed: " . json_encode($sendMsgRes) . "\n";
            $report['4. Chat Box Feature'] = "FAILED (Student Send)";
        }
    } else {
        echo "   ❌ Init student chat failed: " . json_encode($initChatRes) . "\n";
        $report['4. Chat Box Feature'] = "FAILED (Init Chat)";
    }
} else {
    $report['4. Chat Box Feature'] = "SKIPPED";
}

echo "\n----------------------------------------------------\n";

// ----------------------------------------------------
// 5. WORKSHEET / AI PDF EXAM CONNECTIVITY
// ----------------------------------------------------
echo "5. Testing Worksheet & AI PDF Folders... \n";

$pdfFoldersRes = makeGetRequest("$baseUrl/get_pdf_folders.php?user_id=$testStudentId");
if (isset($pdfFoldersRes['status']) && $pdfFoldersRes['status'] === 'success') {
    echo "   ✅ Student App fetched AI PDF Worksheet Folders successfully!\n";
    $report['5. Worksheet / AI PDF'] = "PASSED";
} else {
    echo "   ❌ get_pdf_folders failed: " . json_encode($pdfFoldersRes) . "\n";
    $report['5. Worksheet / AI PDF'] = "FAILED";
}

echo "\n=======================================================\n";
echo "📊 FINAL INTEGRATION & CONNECTIVITY AUDIT SUMMARY:\n";
echo "=======================================================\n";
foreach ($report as $feature => $status) {
    $icon = strpos($status, 'PASSED') !== false ? '✅' : '❌';
    echo str_pad($feature, 28) . ": $icon $status\n";
}
?>
