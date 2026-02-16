-- Fix Class 3 English Chapter Count Mismatch
-- This script merges duplicate Class 3 English subject entries
-- Created: 2026-02-15

USE veeru_db;

-- Start transaction for safety
START TRANSACTION;

-- Step 1: Update the chapter from subject 33 to subject 13
-- Move chapter 30 ("Chapter 1: Introduction to English") to subject 13
UPDATE chapters 
SET subject_id = 13, 
    chapter_order = 4 
WHERE chapter_id = 30 AND subject_id = 33;

-- Note: MCQs, notes, videos, and flashcards are linked via chapter_id,
-- so they automatically follow the chapter. No additional updates needed.

-- Step 2: Delete the orphaned subject entry (subject_id 33)
-- Now that all chapters have been moved, we can safely delete this
DELETE FROM subjects 
WHERE subject_id = 33;

-- Step 3: Delete orphaned subjects from duplicate class entries
-- Remove any subjects still attached to class_id 19 and 29
DELETE FROM subjects 
WHERE class_id IN (19, 29);

-- Step 4: Delete the duplicate class entries
-- Remove class_id 19 and 29 (both are "CLASS 3" duplicates)
DELETE FROM classes 
WHERE class_id IN (19, 29);

-- Verify the changes
SELECT 'After Migration - Class 3 Entries' as info;
SELECT class_id, class_name 
FROM classes 
WHERE class_name LIKE '%Class 3%';

SELECT 'After Migration - English Subjects for Class 3' as info;
SELECT s.subject_id, s.subject_name, s.class_id, c.class_name 
FROM subjects s 
INNER JOIN classes c ON s.class_id = c.class_id 
WHERE s.subject_name LIKE '%English%' AND c.class_name LIKE '%Class 3%';

SELECT 'After Migration - Chapters for English (subject_id 13)' as info;
SELECT chapter_id, chapter_name, subject_id, chapter_order 
FROM chapters 
WHERE subject_id = 13 
ORDER BY chapter_order;

-- If everything looks good, commit the transaction
-- COMMIT;

-- If something went wrong, you can rollback
-- ROLLBACK;

-- IMPORTANT: Review the output above before committing!
-- Uncomment COMMIT above and run again to apply changes permanently.
