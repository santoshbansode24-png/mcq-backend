<?php
/**
 * Migrate Data to CBSE
 * Updates all existing classes and users to be CBSE
 */
require_once '../config/db.php';

try {
    // 1. Update all classes to CBSE
    $stmtClasses = $pdo->prepare("UPDATE classes SET board_type = 'CBSE'");
    $stmtClasses->execute();
    $classCount = $stmtClasses->rowCount();
    echo "✅ Updated $classCount classes to CBSE Board.<br>";

    // 2. Update all users to CBSE
    $stmtUsers = $pdo->prepare("UPDATE users SET board_type = 'CBSE'");
    $stmtUsers->execute();
    $userCount = $stmtUsers->rowCount();
    echo "✅ Updated $userCount users to CBSE Board.<br>";

    echo "🎉 Migration complete! All previous data is now under CBSE.";

} catch (PDOException $e) {
    echo "❌ Error during migration: " . $e->getMessage();
}
?>
