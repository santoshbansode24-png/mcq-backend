-- ============================================
-- Language Support Migration for MCQs
-- ============================================
-- This script adds 'medium' column to mcqs table
-- to support English and Marathi language filtering
-- ============================================

-- Step 1: Add medium column to mcqs table
ALTER TABLE mcqs 
ADD COLUMN medium VARCHAR(20) DEFAULT 'english' 
AFTER difficulty;

-- Step 2: Add index for better query performance
ALTER TABLE mcqs 
ADD INDEX idx_chapter_medium (chapter_id, medium);

-- Step 3: Verify the changes
DESCRIBE mcqs;

-- Step 4: Check existing data (all should be 'english' by default)
SELECT mcq_id, chapter_id, medium, LEFT(question, 50) as question_preview 
FROM mcqs 
LIMIT 10;

-- ============================================
-- NOTES:
-- - All existing MCQs will default to 'english'
-- - Admin can now upload Marathi MCQs with medium='marathi'
-- - API will filter by medium parameter
-- ============================================
