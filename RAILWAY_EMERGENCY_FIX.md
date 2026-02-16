# 🚨 CRITICAL: Restart Railway Database

Your screenshot shows that your **MySQL Database has STOPPED RUNNING**. This is why the command failed and why your live app is likely down.

## Step 1: Restart MySQL Service

1. Go to [Railway Dashboard](https://railway.app?project=veera)
2. Click on the **MySQL** card (it likely has a red icon or says "Crashed")
3. Click the **Restart** or **Redeploy** button
4. **WAIT** until it turns 🟢 **Green (Active)** again.

---

## Step 2: Run the Fix (Web Console Method)

Since your local terminal is not linked to Railway, **do not use PowerShell**. Use the Web Console instead.

1. Click on the **MySQL** card in Railway.
2. Click on the **"Data"** tab.
3. You will see a query editor / console.
4. Copy the code below and paste it there:

```sql
-- 1. Create content_progress table (Fixes 500 Error)
CREATE TABLE IF NOT EXISTS content_progress (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add Missing "RED FLOWER" Chapter
INSERT INTO chapters (chapter_id, subject_id, chapter_name, description, chapter_order, created_at)
SELECT * FROM (SELECT 137, 13, 'RED FLOWER', '', 5, '2026-02-15 10:12:32') AS tmp
WHERE NOT EXISTS (
    SELECT chapter_id FROM chapters WHERE chapter_id = 137
) LIMIT 1;
```

5. Click **Run Query**.

---

## Step 3: Verify

1. After running the query, open your **Live Veeru App**.
2. Check **Class 3 -> English**. You should see **5 Chapters**.
3. Try marking a set as complete. It should work now.

**Let me know if the database restarts successfully!**
