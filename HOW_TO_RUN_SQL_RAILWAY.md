# How to Run SQL in Railway (Web Console)

You don't need to install anything. You can do this right in your browser.

## Step 1: Open Railway Data Tab
1.  Go to your [Railway Dashboard](https://railway.app).
2.  Click on the **MySQL** card.
3.  Click on the **Data** tab (it's between "Deployments" and "Backups").

## Step 2: Paste the Code
1.  On the "Data" tab, you will see a big text box (Query Editor).
2.  **Delete any existing text** in that box.
3.  **Paste** the SQL code below:

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

## Step 3: Run It
1.  Click the **Run Query** button (usually at the bottom right of the text box, or run icon).
2.  You should see a message saying "Success" or "0 rows affected" (if table already exists).

**That's it! Your live app is fixed.**
