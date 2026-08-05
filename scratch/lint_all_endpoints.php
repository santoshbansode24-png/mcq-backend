<?php
/**
 * Syntax Lint Checker for All Student App Endpoints
 */
$phpBinary = 'C:\\xampp\\php\\php.exe';

$filesToLint = [
    'backend/api/login.php',
    'api/login.php',
    'backend/api/register.php',
    'api/register.php',
    'backend/api/google_login.php',
    'backend/api/send_otp.php',
    'backend/api/verify_otp.php',
    'backend/api/reset_password.php',
    'backend/api/student/join_classroom.php',
    'api/student/join_classroom.php',
    'backend/api/student/get_joined_classes.php',
    'api/student/get_joined_classes.php',
    'backend/api/get_notifications.php',
    'api/get_notifications.php',
    'backend/api/chat/init_student_chat.php',
    'api/chat/init_student_chat.php',
    'backend/api/chat/get_messages.php',
    'api/chat/get_messages.php',
    'backend/api/chat/send_message.php',
    'api/chat/send_message.php',
    'backend/api/get_classes.php',
    'api/get_classes.php',
    'backend/api/update_student_class.php',
    'api/update_student_class.php',
    'backend/api/get_subjects.php',
    'api/get_subjects.php',
    'backend/api/get_chapters.php',
    'api/get_chapters.php',
    'backend/api/get_chapter_progress.php',
    'api/get_chapter_progress.php',
    'backend/api/get_mcqs.php',
    'api/get_mcqs.php',
    'backend/api/record_mcq_attempt.php',
    'api/record_mcq_attempt.php',
    'backend/api/get_notes.php',
    'api/get_notes.php',
    'backend/api/get_videos.php',
    'api/get_videos.php',
    'backend/api/get_flashcards.php',
    'api/get_flashcards.php',
    'backend/api/get_quick_revision.php',
    'api/get_quick_revision.php',
    'backend/api/get_set_status.php',
    'api/get_set_status.php',
    'backend/api/mark_set_completed.php',
    'api/mark_set_completed.php',
    'backend/api/save_bookmark.php',
    'backend/api/get_bookmarks.php',
    'backend/api/student/check_live_exam.php',
    'backend/api/submit_class_exam.php',
    'backend/api/get_exam_history.php',
    'backend/api/get_mcq_leaderboard.php',
    'api/get_mcq_leaderboard.php',
    'backend/api/get_student_analytics.php',
    'api/get_student_analytics.php',
    'backend/api/get_badges.php',
    'api/get_badges.php',
    'backend/api/mental_math_get_progress.php',
    'backend/api/update_math_progress.php',
    'backend/api/vocab_get_set.php',
    'backend/api/vocab_get_stats.php',
    'backend/api/vocab_complete_set.php'
];

$baseDir = dirname(__DIR__);
$errors = 0;

echo "Linting " . count($filesToLint) . " PHP files...\n";

foreach ($filesToLint as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (!file_exists($fullPath)) {
        echo "FAIL (File Not Found): $file\n";
        $errors++;
        continue;
    }
    
    $cmd = "\"$phpBinary\" -l \"$fullPath\" 2>&1";
    $output = shell_exec($cmd);
    
    if (strpos($output, 'No syntax errors detected') !== false) {
        echo "PASS: $file\n";
    } else {
        echo "FAIL (Syntax Error): $file -> $output\n";
        $errors++;
    }
}

echo "\nSummary: " . (count($filesToLint) - $errors) . " PASSED, $errors FAILED.\n";
?>
