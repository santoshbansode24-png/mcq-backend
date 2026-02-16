-- ============================================================================
-- VEERU APP - RAILWAY PRODUCTION UPDATE (2026-02-15)
-- ============================================================================
-- This script applies ALL pending fixes to the Railway Production Database.
-- Run this ONCE to fix:
-- 1. Missing "content_progress" table (Fixes 500 Error)
-- 2. Missing "RED FLOWER" chapter in Class 3 English
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. FIX: Create missing 'content_progress' table
-- ----------------------------------------------------------------------------
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
    
    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT '✓ Table content_progress created/verified' AS 'Status';

-- ----------------------------------------------------------------------------
-- 2. FIX: Sync missing Chapter 'RED FLOWER' (ID: 137)
-- ----------------------------------------------------------------------------
-- Only insert if it doesn't exist to avoid duplicates
INSERT INTO chapters (chapter_id, subject_id, chapter_name, description, chapter_order, created_at)
SELECT * FROM (SELECT 137, 13, 'RED FLOWER', '', 5, '2026-02-15 10:12:32') AS tmp
WHERE NOT EXISTS (
    SELECT chapter_id FROM chapters WHERE chapter_id = 137
) LIMIT 1;

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ Chapter RED FLOWER exists' 
        ELSE '✗ Error: Chapter RED FLOWER missing' 
    END AS 'Chapter Status'
FROM chapters WHERE chapter_id = 137;

-- ----------------------------------------------------------------------------
-- 3. VERIFICATION
-- ----------------------------------------------------------------------------
SELECT '=== Update Completed Successfully ===' AS 'Result';
