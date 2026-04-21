<?php
require_once '../config/db.php';

echo "<h2>Mental Maths Schema Update</h2>";

try {
    /** @var PDO $pdo */

    $sql1 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS mental_math_level INT DEFAULT 1;";
    $pdo->exec($sql1);
    echo "<p style='color: green'>✅ Column <strong>mental_math_level</strong> added/checked.</p>";

    $sql2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS abacus_level INT DEFAULT 1;";
    $pdo->exec($sql2);
    echo "<p style='color: green'>✅ Column <strong>abacus_level</strong> added/checked.</p>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color: orange'>⚠️ Columns already exist.</p>";
    } else {
        echo "<p style='color: red'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

echo "<p>Done.</p>";
?>
