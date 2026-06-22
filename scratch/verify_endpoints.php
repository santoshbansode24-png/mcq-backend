<?php
$php = 'C:\xampp\php\php.exe';

require_once __DIR__ . '/../config/db.php';
$student = $pdo->query("SELECT user_id, name FROM users WHERE user_type = 'student' LIMIT 1")->fetch();
$teacher = $pdo->query("SELECT user_id, name FROM users WHERE user_type = 'teacher' LIMIT 1")->fetch();

echo "--- START ISOLATED VERIFICATION ---\n\n";

function extractAndDecodeJson($output) {
    $start = strpos($output, '{');
    if ($start === false) return null;
    $json = substr($output, $start);
    return json_decode($json, true);
}

if ($student) {
    $sid = $student['user_id'];
    echo "Testing analytics for student ID: $sid ({$student['name']})\n";
    
    $cmd = "\"$php\" -r \"\$_GET['user_id'] = $sid; chdir('api'); include 'get_student_analytics.php';\" 2>&1";
    $output = shell_exec($cmd);
    
    $decoded = extractAndDecodeJson($output);
    if ($decoded && $decoded['status'] === 'success') {
        echo "✅ Student Analytics parsed successfully!\n";
        echo "Overview: " . json_encode($decoded['data']['overview']) . "\n";
        echo "Live Overview: " . json_encode($decoded['data']['live_overview']) . "\n";
        echo "Monthly Trend Count: " . count($decoded['data']['monthly_trend'] ?? []) . "\n";
        echo "Live History Count: " . count($decoded['data']['live_history'] ?? []) . "\n";
    } else {
        echo "❌ Student Analytics failed to decode:\n";
        echo $output . "\n";
    }
} else {
    echo "ℹ️ No student found in users table.\n";
}

echo "\n----------------------------------------\n\n";

if ($teacher) {
    $tid = $teacher['user_id'];
    echo "Testing exams for teacher ID: $tid ({$teacher['name']})\n";
    
    $cmd = "\"$php\" -r \"\$_GET['teacher_id'] = $tid; chdir('backend/api'); include 'get_teacher_exams.php';\" 2>&1";
    $output = shell_exec($cmd);
    
    $decoded = extractAndDecodeJson($output);
    if ($decoded && $decoded['status'] === 'success') {
        echo "✅ Teacher Exams parsed successfully!\n";
        echo "Total Exams Found: " . count($decoded['data'] ?? []) . "\n";
        if (count($decoded['data'] ?? []) > 0) {
            $first = $decoded['data'][0];
            echo "First Exam Title: {$first['title']}\n";
            echo "Submissions: {$first['total_submissions']}\n";
            echo "Average Score: {$first['average_score']}%\n";
        } else {
            echo "ℹ️ No teacher exams found.\n";
        }
    } else {
        echo "❌ Teacher Exams failed to decode:\n";
        echo $output . "\n";
    }
} else {
    echo "ℹ️ No teacher found in users table.\n";
}

echo "\n--- END VERIFICATION ---\n";
