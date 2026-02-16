<?php
/**
 * ONE-CLICK DEPLOY: PROTECTION SYSTEM
 * Run this: http://localhost/veeru/deploy_prevention.php
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $public_url = trim($_POST['public_url']);

    try {
        // Parse URL
        $parsed = parse_url($public_url);
        if (!$parsed || !isset($parsed['host'])) { throw new Exception("Invalid URL. Check MYSQL_PUBLIC_URL."); }

        $host = $parsed['host'];
        $port = isset($parsed['port']) ? $parsed['port'] : 3306;
        $user = $parsed['user'];
        $pass = $parsed['pass'];
        $dbname = ltrim($parsed['path'], '/');

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        echo "<h3>1. Connected to Railway Database...</h3>";

        // ==========================================================
        // STEP 1: CLEANUP DUPLICATES (Fix Class 3 & Others)
        // ==========================================================
        
        // 1.1 Fix Class 3 Duplicates (Specific IDs)
        $cleanup_sql = "
            UPDATE chapters SET subject_id = 13, chapter_order = 4 WHERE chapter_id = 30 AND subject_id = 33;
            DELETE FROM subjects WHERE subject_id = 33;
            DELETE FROM subjects WHERE class_id IN (19, 29);
            DELETE FROM classes WHERE class_id IN (19, 29);
        ";
        try {
            $pdo->exec($cleanup_sql);
            echo "<p style='color:green'>✓ Cleanup Step 1: Class 3 Duplicates Removed</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange'>⚠ Cleanup Step 1 Warning: " . $e->getMessage() . "</p>";
        }

        // 1.2 Fix Specific Duplicate Chapter: 'संख्या मालिका' (subject_id 93)
        // We will keep the one with the lowest ID and delete the rest
        try {
            // Find duplicates for 'संख्या मालिका'
            $dupe_fix_sql = "
                DELETE c1 FROM chapters c1
                INNER JOIN chapters c2 
                WHERE 
                    c1.chapter_id > c2.chapter_id AND 
                    c1.chapter_name = 'संख्या मालिका' AND 
                    c1.subject_id = 93 AND 
                    c2.chapter_name = 'संख्या मालिका' AND 
                    c2.subject_id = 93;
            ";
            $pdo->exec($dupe_fix_sql);
            echo "<p style='color:green'>✓ Cleanup Step 2: Removed duplicate 'संख्या मालिका' chapters</p>";
        } catch (Exception $e) {
             echo "<p style='color:orange'>⚠ Cleanup Step 2 Warning: " . $e->getMessage() . "</p>";
        }

        // ==========================================================
        // STEP 2: ADD UNIQUE CONSTRAINTS
        // ==========================================================
        // Fix: Use 'board_type' instead of 'board_id' for classes table if that's the column name
        // We learned from error that 'board_id' doesn't exist.
        
        $constraints = [
            // Changed board_id -> board_type
            "ALTER TABLE classes ADD CONSTRAINT unique_class_per_board UNIQUE (class_name, board_type)",
            "ALTER TABLE subjects ADD CONSTRAINT unique_subject_per_class UNIQUE (subject_name, class_id)",
            "ALTER TABLE chapters ADD CONSTRAINT unique_chapter_per_subject UNIQUE (chapter_name, subject_id)"
        ];

        foreach ($constraints as $sql) {
            try {
                $pdo->exec($sql);
                echo "<p style='color:green'>✓ Constraint Added</p>";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "Duplicate key name") !== false) {
                    echo "<p style='color:blue'>ℹ Constraint already exists</p>";
                } elseif (strpos($e->getMessage(), "Duplicate entry") !== false) {
                     echo "<p style='color:red'>❌ FAILED to add constraint: " . $e->getMessage() . "</p>";
                } else {
                    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
                }
            }
        }

        // ==========================================================
        // STEP 3: ADD TRIGGERS
        // ==========================================================
        
        $triggers = [];
        
        // Changed board_id -> board_type
        $triggers['before_insert_class'] = "
            CREATE TRIGGER before_insert_class BEFORE INSERT ON classes FOR EACH ROW
            BEGIN
                DECLARE duplicate_count INT;
                SET NEW.class_name = UPPER(TRIM(NEW.class_name));
                SELECT COUNT(*) INTO duplicate_count FROM classes WHERE UPPER(class_name) = NEW.class_name AND board_type = NEW.board_type;
                IF duplicate_count > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate class!'; END IF;
            END
        ";
        
        $triggers['before_insert_subject'] = "
            CREATE TRIGGER before_insert_subject BEFORE INSERT ON subjects FOR EACH ROW
            BEGIN
                DECLARE duplicate_count INT;
                SET NEW.subject_name = UPPER(TRIM(NEW.subject_name));
                SELECT COUNT(*) INTO duplicate_count FROM subjects WHERE UPPER(subject_name) = NEW.subject_name AND class_id = NEW.class_id;
                IF duplicate_count > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate subject!'; END IF;
            END
        ";

        $triggers['before_insert_chapter'] = "
            CREATE TRIGGER before_insert_chapter BEFORE INSERT ON chapters FOR EACH ROW
            BEGIN
                DECLARE duplicate_count INT;
                SET NEW.chapter_name = UPPER(TRIM(NEW.chapter_name));
                SELECT COUNT(*) INTO duplicate_count FROM chapters WHERE UPPER(chapter_name) = NEW.chapter_name AND subject_id = NEW.subject_id;
                IF duplicate_count > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate chapter!'; END IF;
            END
        ";

        foreach ($triggers as $name => $sql) {
            try {
                $pdo->exec("DROP TRIGGER IF EXISTS $name");
                $pdo->exec($sql);
                echo "<p style='color:green'>✓ Trigger '$name' Created</p>";
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Trigger Error: " . $e->getMessage() . "</p>";
            }
        }

        echo "<h1>🎉 DEPLOYMENT COMPLETE!</h1>";
        exit;

    } catch (Throwable $e) {
        echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Deploy Prevention System</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #e0f2f1; }
        .card { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 15px; margin: 10px 0 20px; border: 2px solid #009688; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 15px; background: #009688; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #00796b; }
        label { font-weight: bold; color: #333; font-size: 18px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="text-align:center">🛡️ Deploy Protection System</h1>
        <p>This will: <br>1. Cleanup Duplicates <br>2. Add UNIQUE Constraints <br>3. Add Safety Triggers</p>
        
        <form method="POST">
            <label>Paste MYSQL_PUBLIC_URL here:</label>
            <input type="text" name="public_url" required placeholder="mysql://root:..." value="<?php echo isset($_POST['public_url']) ? htmlspecialchars($_POST['public_url']) : ''; ?>">
            
            <button type="submit">DEPLOY PROTECTION 🛡️</button>
        </form>
    </div>
</body>
</html>
