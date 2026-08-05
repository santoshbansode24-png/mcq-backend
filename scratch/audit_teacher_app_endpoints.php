<?php
// scratch/audit_teacher_app_endpoints.php
set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

$baseUrl = 'https://api.veeruapp.in/backend/api';
$teacherId = 2; // Valid Teacher ID (John Teacher)

echo "=======================================================\n";
echo "🚀 VEERU TEACHER APP COMPREHENSIVE ENDPOINT AUDIT\n";
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

function makeMultipartPostRequest($url, $fields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$results = [];

// 1. Get Teacher Classes
echo "1. Testing Get Teacher Classes (teacher/get_classes.php)... ";
$res1 = makePostRequest("$baseUrl/teacher/get_classes.php", ['teacher_id' => $teacherId]);
if (isset($res1['status']) && $res1['status'] === 'success') {
    echo "✅ PASSED (" . count($res1['data']) . " classrooms found)\n";
    $results['1. Get Teacher Classes'] = 'PASSED';
} else {
    echo "❌ FAILED: " . json_encode($res1) . "\n";
    $results['1. Get Teacher Classes'] = 'FAILED';
}

// 2. Create Classroom
echo "2. Testing Create Classroom (teacher/create_classroom.php)... ";
$res2 = makePostRequest("$baseUrl/teacher/create_classroom.php", [
    'teacher_id' => $teacherId,
    'class_id' => 10,
    'division_name' => 'Orchid',
    'name' => 'John Teacher',
    'mobile' => '9876543210'
]);
if (isset($res2['status']) && $res2['status'] === 'success') {
    $createdClassroomId = $res2['data']['classroom_id'] ?? $res2['data']['class_id'];
    $createdClassCode = $res2['data']['class_code'];
    echo "✅ PASSED (Created Code: '$createdClassCode', ID: $createdClassroomId)\n";
    $results['2. Create Classroom'] = 'PASSED';
} else {
    echo "❌ FAILED: " . json_encode($res2) . "\n";
    $results['2. Create Classroom'] = 'FAILED';
}

// 3. Get Class Updates Feed
echo "3. Testing Fetch Class Updates Feed (get_class_updates.php)... ";
if (isset($createdClassroomId)) {
    $res3 = makeGetRequest("$baseUrl/get_class_updates.php?user_id=$teacherId&class_id=$createdClassroomId");
    if (isset($res3['status']) && $res3['status'] === 'success') {
        echo "✅ PASSED (" . count($res3['data']) . " updates in feed)\n";
        $results['3. Class Updates Feed'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res3) . "\n";
        $results['3. Class Updates Feed'] = 'FAILED';
    }
}

// 4. Send Notification / Announcement
echo "4. Testing Post Announcement (teacher/send_notification.php)... ";
if (isset($createdClassroomId)) {
    $res4 = makePostRequest("$baseUrl/teacher/send_notification.php", [
        'teacher_id' => $teacherId,
        'class_id' => $createdClassroomId,
        'title' => 'Important Exam Notice',
        'message' => 'Bring your hall ticket tomorrow for the exam.'
    ]);
    if (isset($res4['status']) && ($res4['status'] === 'success' || $res4['status'] === 201)) {
        $createdUpdateId = $res4['data']['notification_id'] ?? 1;
        echo "✅ PASSED (Notification ID: $createdUpdateId)\n";
        $results['4. Post Announcement'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res4) . "\n";
        $results['4. Post Announcement'] = 'FAILED';
    }
}

// 5. Get Class Students List
echo "5. Testing Get Students List (teacher/get_students.php)... ";
if (isset($createdClassroomId)) {
    $res5 = makePostRequest("$baseUrl/teacher/get_students.php", ['class_id' => $createdClassroomId]);
    if (isset($res5['status']) && $res5['status'] === 'success') {
        echo "✅ PASSED (" . count($res5['data']) . " enrolled students)\n";
        $results['5. Class Students List'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res5) . "\n";
        $results['5. Class Students List'] = 'FAILED';
    }
}

// 6. Curriculum Chapters & MCQs
echo "6. Testing Curriculum & Question Bank (get_chapters.php & get_mcqs.php)... ";
$res6a = makeGetRequest("$baseUrl/get_chapters.php?subject_id=1&class_id=10");
if (isset($res6a['status']) && $res6a['status'] === 'success' && !empty($res6a['data'])) {
    $chapterId = $res6a['data'][0]['chapter_id'] ?? 1;
    $res6b = makeGetRequest("$baseUrl/get_mcqs.php?chapter_id=$chapterId");
    if (isset($res6b['status']) && $res6b['status'] === 'success') {
        echo "✅ PASSED (" . count($res6b['data']) . " MCQs loaded for chapter $chapterId)\n";
        $results['6. Curriculum & Question Bank'] = 'PASSED';
    } else {
        echo "❌ FAILED get_mcqs: " . json_encode($res6b) . "\n";
        $results['6. Curriculum & Question Bank'] = 'FAILED (MCQs)';
    }
} else {
    echo "❌ FAILED get_chapters: " . json_encode($res6a) . "\n";
    $results['6. Curriculum & Question Bank'] = 'FAILED (Chapters)';
}

// 7. Create Live Exam
echo "7. Testing Create Live Exam (teacher/create_live_exam.php)... ";
if (isset($createdClassroomId)) {
    $res7 = makePostRequest("$baseUrl/teacher/create_live_exam.php", [
        'teacher_id' => $teacherId,
        'class_id' => $createdClassroomId,
        'chapter_id' => 1,
        'title' => 'Unit Test Live Quiz',
        'duration_minutes' => 15,
        'selected_question_ids' => [1, 2, 3]
    ]);
    if (isset($res7['status']) && $res7['status'] === 'success') {
        $liveExamId = $res7['data']['exam_id'] ?? 1;
        echo "✅ PASSED (Live Exam ID: $liveExamId)\n";
        $results['7. Create Live Exam'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res7) . "\n";
        $results['7. Create Live Exam'] = 'FAILED';
    }
}

// 8. Get Live Exams List & Leaderboard
echo "8. Testing Live Exams List & Leaderboard (teacher/get_live_exams_list.php & leaderboard)... ";
if (isset($createdClassroomId)) {
    $res8a = makeGetRequest("$baseUrl/teacher/get_live_exams_list.php?class_id=$createdClassroomId");
    if (isset($res8a['status']) && $res8a['status'] === 'success') {
        if (isset($liveExamId)) {
            $res8b = makeGetRequest("$baseUrl/teacher/get_live_exam_leaderboard.php?live_exam_id=$liveExamId");
            if (isset($res8b['status']) && $res8b['status'] === 'success') {
                echo "✅ PASSED (Leaderboard loaded successfully)\n";
                $results['8. Live Exam Leaderboard'] = 'PASSED';
            } else {
                echo "❌ FAILED Leaderboard: " . json_encode($res8b) . "\n";
                $results['8. Live Exam Leaderboard'] = 'FAILED (Leaderboard)';
            }
        } else {
            echo "✅ PASSED (Live exams list loaded)\n";
            $results['8. Live Exam Leaderboard'] = 'PASSED';
        }
    } else {
        echo "❌ FAILED Exams List: " . json_encode($res8a) . "\n";
        $results['8. Live Exam Leaderboard'] = 'FAILED (Exams List)';
    }
}

// 9. End Live Exam
echo "9. Testing End Live Exam (teacher/end_live_exam.php)... ";
if (isset($liveExamId) && isset($createdClassroomId)) {
    $res9 = makePostRequest("$baseUrl/teacher/end_live_exam.php", [
        'teacher_id' => $teacherId,
        'class_id' => $createdClassroomId,
        'exam_id' => $liveExamId,
        'live_exam_id' => $liveExamId
    ]);
    if (isset($res9['status']) && $res9['status'] === 'success') {
        echo "✅ PASSED (Live exam ended successfully)\n";
        $results['9. End Live Exam'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res9) . "\n";
        $results['9. End Live Exam'] = 'FAILED';
    }
}

// 10. Chat Messages Feed
echo "10. Testing Chat Screen Messages (chat/get_messages.php & send_message.php)... ";
if (isset($createdClassCode)) {
    $res10a = makePostRequest("$baseUrl/chat/send_message.php", [
        'sender_id' => $teacherId,
        'class_code' => $createdClassCode,
        'message_text' => 'Welcome students to our new class broadcast channel!'
    ]);
    if (isset($res10a['status']) && $res10a['status'] === 'success') {
        $res10b = makeGetRequest("$baseUrl/chat/get_messages.php?class_code=$createdClassCode&user_id=$teacherId");
        if (isset($res10b['status']) && $res10b['status'] === 'success') {
            echo "✅ PASSED (Chat messages loaded successfully)\n";
            $results['10. Chat Screen & Messaging'] = 'PASSED';
        } else {
            echo "❌ FAILED get_messages: " . json_encode($res10b) . "\n";
            $results['10. Chat Screen & Messaging'] = 'FAILED (Get Messages)';
        }
    } else {
        echo "❌ FAILED send_message: " . json_encode($res10a) . "\n";
        $results['10. Chat Screen & Messaging'] = 'FAILED (Send Message)';
    }
}

// 11. Send Worksheet Material (FormData)
echo "11. Testing Send Worksheet Material (teacher/upload_class_material.php)... ";
if (isset($createdClassroomId)) {
    $res11 = makeMultipartPostRequest("$baseUrl/teacher/upload_class_material.php", [
        'teacher_id' => $teacherId,
        'class_id' => $createdClassroomId,
        'title' => 'Math Revision Worksheet 1',
        'message' => 'Please solve this worksheet by Friday.',
        'update_type' => 'worksheet',
        'payload' => json_encode(['type' => 'worksheet_data', 'subjectNames' => 'Mathematics'])
    ]);
    if (isset($res11['status']) && $res11['status'] === 'success') {
        echo "✅ PASSED (Worksheet uploaded & dispatched to class)\n";
        $results['11. Send Worksheet Material'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res11) . "\n";
        $results['11. Send Worksheet Material'] = 'FAILED';
    }
}

// 12. Delete Classroom Cleanup
echo "12. Testing Delete Classroom Cleanup (teacher/delete_class.php)... ";
if (isset($createdClassroomId)) {
    $res12 = makePostRequest("$baseUrl/teacher/delete_class.php", [
        'teacher_id' => $teacherId,
        'class_id' => $createdClassroomId
    ]);
    if (isset($res12['status']) && $res12['status'] === 'success') {
        echo "✅ PASSED (Classroom deleted cleanly)\n";
        $results['12. Delete Classroom'] = 'PASSED';
    } else {
        echo "❌ FAILED: " . json_encode($res12) . "\n";
        $results['12. Delete Classroom'] = 'FAILED';
    }
}

echo "\n=======================================================\n";
echo "📊 VEERU TEACHER APP AUDIT SUMMARY:\n";
echo "=======================================================\n";
foreach ($results as $feature => $status) {
    $icon = strpos($status, 'PASSED') !== false ? '✅' : '❌';
    echo str_pad($feature, 32) . ": $icon $status\n";
}
?>
