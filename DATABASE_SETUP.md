# Database Setup for Chapter Progress Tracking

## Run this SQL in your MySQL database (mcq_project_v2)

You can run this via phpMyAdmin or MySQL command line.

### Option 1: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select database `mcq_project_v2`
3. Click "SQL" tab
4. Copy and paste the SQL below
5. Click "Go"

### Option 2: Using MySQL Command Line
```bash
mysql -u root -p mcq_project_v2 < c:\xampp\htdocs\mcq project2.0\backend\schema_mcq_attempts.sql
```

### SQL Schema

```sql
CREATE TABLE IF NOT EXISTS mcq_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mcq_id INT NOT NULL,
    chapter_id INT NOT NULL,
    selected_answer VARCHAR(1),
    correct_answer VARCHAR(1),
    is_correct BOOLEAN,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (mcq_id) REFERENCES mcqs(mcq_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE,
    INDEX idx_user_chapter (user_id, chapter_id),
    INDEX idx_user_mcq (user_id, mcq_id),
    INDEX idx_correctness (user_id, chapter_id, is_correct)
);
```

## Verification

After running the SQL, verify the table was created:

```sql
DESCRIBE mcq_attempts;
```

You should see 8 columns: attempt_id, user_id, mcq_id, chapter_id, selected_answer, correct_answer, is_correct, attempted_at
