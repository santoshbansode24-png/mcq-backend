<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $public_url = trim($_POST['public_url']);

    try {
        // Parse the URL: mysql://user:pass@host:port/dbname
        $parsed = parse_url($public_url);
        
        if (!$parsed || !isset($parsed['host'])) {
            throw new Exception("Invalid URL format. Please copy MYSQL_PUBLIC_URL exactly.");
        }

        $host = $parsed['host'];
        $port = isset($parsed['port']) ? $parsed['port'] : 3306;
        $user = $parsed['user'];
        $pass = $parsed['pass'];
        $dbname = ltrim($parsed['path'], '/');

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 1. Create content_progress
        $sql1 = "CREATE TABLE IF NOT EXISTS content_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            chapter_id INT NOT NULL,
            content_type ENUM('mcq', 'flashcard') NOT NULL,
            set_index INT NOT NULL DEFAULT 0,
            status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
            score INT DEFAULT 0,
            total INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_content_set (user_id, chapter_id, content_type, set_index),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql1);
        echo "<h2 style='color:green'>✅ Table 'content_progress' created!</h2>";

        // 2. Add Red Flower Chapter
        $sql2 = "INSERT INTO chapters (chapter_id, subject_id, chapter_name, description, chapter_order, created_at)
        SELECT * FROM (SELECT 137, 13, 'RED FLOWER', '', 5, '2026-02-15 10:12:32') AS tmp
        WHERE NOT EXISTS (
            SELECT chapter_id FROM chapters WHERE chapter_id = 137
        ) LIMIT 1;";
        
        $pdo->exec($sql2);
        echo "<h2 style='color:green'>✅ Chapter 'RED FLOWER' synced!</h2>";
        
        echo "<h1>🎉 SUCCESS! YOUR APP IS FIXED.</h1>";
        exit;

    } catch (Throwable $e) {
        echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Railway Database</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f0f9ff; }
        .card { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 15px; margin: 10px 0 20px; border: 2px solid #007bff; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        label { font-weight: bold; color: #333; font-size: 18px; }
        .help-text { color: #666; margin-bottom: 20px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="text-align:center">🛠️ Fix Railway Database (Easy Mode)</h1>
        
        <div class="help-text">
            1. Go to Railway Dashboard &rarr; MySQL &rarr; Variables.<br>
            2. Find <code>MYSQL_PUBLIC_URL</code>.<br>
            3. Copy the <b>whole value</b> (starts with <code>mysql://</code>).<br>
            4. Paste it below.
        </div>
        
        <form method="POST">
            <label>Paste MYSQL_PUBLIC_URL here:</label>
            <input type="text" name="public_url" required placeholder="mysql://root:password@host:port/dbname">
            
            <button type="submit">FIX MY DATABASE 🚀</button>
        </form>
    </div>
</body>
</html>
