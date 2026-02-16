# Fix Missing Table on Railway (500 Error)

## Problem
The `content_progress` table is missing in the database. This causes a **500 Internal Server Error** when checking set completion status.

## Solution
Execute the following SQL script to create the missing table.

---

## Step 1: Copy SQL Script

**File:** `c:\xampp\htdocs\veeru\backend\create_content_progress_table.sql`

Copy the content below:

```sql
-- Create content_progress table for tracking completion of MCQs and Flashcards sets
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
    
    -- Ensure unique record for each set per user
    UNIQUE KEY unique_user_content_set (user_id, chapter_id, content_type, set_index),
    
    -- Foreign keys (optional but recommended)
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Step 2: Execute on Railway MySQL

1. Go to **Railway Dashboard** → **MySQL Service** → **Data** tab.
2. Paste the SQL into the console.
3. Click **Execute**.

**Alternative via MySQL Client:**
```bash
mysql -h <MYSQLHOST> -P <MYSQLPORT> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE> < backend/create_content_progress_table.sql
```

---

## Step 3: Verify

1. Run this query in Railway console:
```sql
SHOW TABLES LIKE 'content_progress';
```
2. It should assume the table exists.

---

## Why this happened?
This table was part of a new feature (Set Completion) but was never created in the database. It was missing in both Local and Railway environments. I fixed it locally for you, but you MUST run it on Railway for the live app to work correctly.
