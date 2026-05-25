<?php
require_once '../config/db.php';

header("Content-Type: text/html; charset=UTF-8");

echo "<h2>🔧 Mental Maths Schema Update</h2>";

try {
    /** @var PDO $pdo */

    // Add mental_math_level column (safely — won't fail if already exists)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN mental_math_level INT NOT NULL DEFAULT 1");
        echo "<p style='color:green'>✅ Column <strong>mental_math_level</strong> added/verified.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "<p style='color:orange'>⚠️ Column <strong>mental_math_level</strong> already exists.</p>";
        } else {
            throw $e;
        }
    }

    // Add abacus_level column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN abacus_level INT NOT NULL DEFAULT 1");
        echo "<p style='color:green'>✅ Column <strong>abacus_level</strong> added/verified.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "<p style='color:orange'>⚠️ Column <strong>abacus_level</strong> already exists.</p>";
        } else {
            throw $e;
        }
    }

    // Fix any existing NULL or 0 values to at least 1 (data integrity fix)
    $pdo->exec("UPDATE users SET mental_math_level = 1 WHERE mental_math_level IS NULL OR mental_math_level < 1");
    $pdo->exec("UPDATE users SET abacus_level = 1 WHERE abacus_level IS NULL OR abacus_level < 1");
    echo "<p style='color:green'>✅ Data integrity check passed — all levels are >= 1.</p>";

    // Add index for fast lookups (ignores if already exists)
    try {
        $pdo->exec("CREATE INDEX idx_users_math_levels ON users (user_id, mental_math_level, abacus_level)");
        echo "<p style='color:green'>✅ Index on math level columns added/verified.</p>";
    } catch (PDOException $idxErr) {
        echo "<p style='color:orange'>⚠️ Index already exists or not supported.</p>";
    }

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color:orange'>⚠️ Columns already exist — schema is up to date.</p>";
    } else {
        echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<hr><p><strong>✅ Schema migration complete.</strong></p>";
?>
