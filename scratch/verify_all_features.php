<?php
/**
 * Student App Feature Audit & Verification Script
 * Checks all PHP API endpoints used by the student app
 */
error_reporting(E_ALL);

$baseDir = dirname(__DIR__);
$backendApiDir = $baseDir . '/backend/api';
$apiDir = $baseDir . '/api';

echo "=========================================\n";
echo "VEERU STUDENT APP FEATURE AUDIT\n";
echo "=========================================\n\n";

$features = [
    "Authentication" => [
        "Login" => ['backend/api/login.php', 'api/login.php'],
        "Register" => ['backend/api/register.php', 'api/register.php'],
        "Google Login" => ['backend/api/google_login.php'],
        "OTP Request" => ['backend/api/send_otp.php'],
        "OTP Verify" => ['backend/api/verify_otp.php'],
        "Reset Password" => ['backend/api/reset_password.php']
    ],
    "Classroom & Communications" => [
        "Join Classroom" => ['backend/api/student/join_classroom.php', 'api/student/join_classroom.php'],
        "Get Joined Classes" => ['backend/api/student/get_joined_classes.php', 'api/student/get_joined_classes.php'],
        "Get Class Updates & Notifications" => ['backend/api/get_notifications.php', 'api/get_notifications.php'],
        "Init Student Chat" => ['backend/api/chat/init_student_chat.php', 'api/chat/init_student_chat.php'],
        "Get Messages" => ['backend/api/chat/get_messages.php', 'api/chat/get_messages.php'],
        "Send Message" => ['backend/api/chat/send_message.php', 'api/chat/send_message.php']
    ],
    "Curriculum & Content Delivery" => [
        "Get Classes List" => ['backend/api/get_classes.php', 'api/get_classes.php'],
        "Update Student Class" => ['backend/api/update_student_class.php', 'api/update_student_class.php'],
        "Get Subjects" => ['backend/api/get_subjects.php', 'api/get_subjects.php'],
        "Get Chapters" => ['backend/api/get_chapters.php', 'api/get_chapters.php'],
        "Get Chapter Progress" => ['backend/api/get_chapter_progress.php', 'api/get_chapter_progress.php'],
        "Get MCQs" => ['backend/api/get_mcqs.php', 'api/get_mcqs.php'],
        "Record MCQ Attempt" => ['backend/api/record_mcq_attempt.php', 'api/record_mcq_attempt.php'],
        "Get Notes" => ['backend/api/get_notes.php', 'api/get_notes.php'],
        "Get Videos" => ['backend/api/get_videos.php', 'api/get_videos.php'],
        "Get Flashcards" => ['backend/api/get_flashcards.php', 'api/get_flashcards.php'],
        "Get Quick Revision" => ['backend/api/get_quick_revision.php', 'api/get_quick_revision.php'],
        "Set Status" => ['backend/api/get_set_status.php', 'api/get_set_status.php'],
        "Mark Set Completed" => ['backend/api/mark_set_completed.php', 'api/mark_set_completed.php'],
        "Bookmarks (Save/Get)" => ['backend/api/save_bookmark.php', 'backend/api/get_bookmarks.php']
    ],
    "Exams & Analytics" => [
        "Check Live Exam" => ['backend/api/student/check_live_exam.php'],
        "Submit Class Exam" => ['backend/api/submit_class_exam.php'],
        "Get Exam History" => ['backend/api/get_exam_history.php'],
        "Get MCQ Leaderboard" => ['backend/api/get_mcq_leaderboard.php', 'api/get_mcq_leaderboard.php'],
        "Get Student Analytics" => ['backend/api/get_student_analytics.php', 'api/get_student_analytics.php'],
        "Get Badges" => ['backend/api/get_badges.php', 'api/get_badges.php']
    ],
    "Vocab & Mental Math" => [
        "Mental Math Progress" => ['backend/api/mental_math_get_progress.php', 'backend/api/update_math_progress.php'],
        "Vocab Set & Stats" => ['backend/api/vocab_get_set.php', 'backend/api/vocab_get_stats.php', 'backend/api/vocab_complete_set.php']
    ]
];

$totalFiles = 0;
$missingFiles = 0;

foreach ($features as $category => $endpointList) {
    echo "--- Category: $category ---\n";
    foreach ($endpointList as $featureName => $paths) {
        foreach ($paths as $relPath) {
            $totalFiles++;
            $fullPath = $baseDir . '/' . $relPath;
            if (file_exists($fullPath)) {
                echo " [OK] $featureName -> $relPath\n";
            } else {
                $missingFiles++;
                echo " [MISSING] $featureName -> $relPath\n";
            }
        }
    }
    echo "\n";
}

echo "Total endpoints checked: $totalFiles\n";
echo "Missing endpoints: $missingFiles\n";
?>
