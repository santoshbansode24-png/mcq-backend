<?php
require_once 'backend/config/db.php';

echo "DEBUGGING USER VISIBILITY\n";
echo "=========================\n";

// 1. Count Total Users
$stmt = $pdo->query("SELECT count(*) FROM users WHERE user_type='student'");
$total = $stmt->fetchColumn();
echo "Total Students in DB: $total\n\n";

// 2. List recent students with their Class and Board info
$sql = "
    SELECT 
        u.user_id, 
        u.name, 
        u.email, 
        u.class_id, 
        u.board_type as user_declared_board,
        c.class_name, 
        c.board_type as class_derived_board
    FROM users u
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.user_type = 'student'
    ORDER BY u.created_at DESC
    LIMIT 10
";

$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

printf("%-20s | %-10s | %-15s | %-15s | %-20s\n", "Name", "ClassID", "User Board", "Class Board", "Status");
echo str_repeat("-", 90) . "\n";

foreach ($users as $u) {
    if (empty($u['class_id'])) {
        $status = "⚠️  ORPHAN (No Class)";
    } elseif (empty($u['class_derived_board'])) {
        $status = "❌ BROKEN (Class not found)";
    } elseif ($u['user_declared_board'] != $u['class_derived_board']) {
        $status = "⚠️  MISMATCH";
    } else {
        $status = "✅ OK (" . $u['class_derived_board'] . ")";
    }
    
    printf("%-20s | %-10s | %-15s | %-15s | %-20s\n", 
        substr($u['name'], 0, 20), 
        $u['class_id'] ?? 'NULL', 
        $u['user_declared_board'] ?? 'NULL', 
        $u['class_derived_board'] ?? 'NULL', 
        $status
    );
}

echo "\nNOTE: Admin Panel ONLY shows users where 'Class Board' matches the selected board.\n";
echo "If Status is ORPHAN or BROKEN, the user is INVISIBLE in Admin Panel.\n";
?>
