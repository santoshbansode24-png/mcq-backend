-- ============================================================================
-- Sync Missing Chapter to Railway Production
-- ============================================================================
-- Purpose: Add the missing "RED FLOWER" chapter to Railway production database
--
-- Issue: Chapter shows in Expo Go (local) but not in live Veeru app (Railway)
-- Solution: Insert the missing chapter into Railway database
--
-- Chapter Details:
-- - chapter_id: 137
-- - chapter_name: RED FLOWER
-- - subject_id: 13 (ENGLISH, Class 3)
-- - chapter_order: 5
-- - No MCQs, videos, or notes yet
-- ============================================================================

-- Check if chapter already exists (should return 0)
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ Chapter does not exist - safe to insert'
        ELSE '✗ WARNING: Chapter already exists! Do not run this script.'
    END AS 'Pre-Check'
FROM chapters
WHERE chapter_id = 137 OR (chapter_name = 'RED FLOWER' AND subject_id = 13);

-- Insert the missing chapter
INSERT INTO chapters (chapter_id, subject_id, chapter_name, description, chapter_order, created_at)
VALUES (137, 13, 'RED FLOWER', '', 5, '2026-02-15 10:12:32');

-- Verify insertion
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ SUCCESS: Chapter inserted successfully'
        ELSE '✗ ERROR: Chapter not found after insertion'
    END AS 'Post-Check'
FROM chapters
WHERE chapter_id = 137;

-- Show all chapters for subject_id 13 (ENGLISH, Class 3)
SELECT '=== All Chapters for Class 3 ENGLISH ===' AS '';
SELECT chapter_id, chapter_name, chapter_order
FROM chapters
WHERE subject_id = 13
ORDER BY chapter_order;

-- Expected output:
-- chapter_id | chapter_name              | chapter_order
-- -----------|---------------------------|---------------
-- 132        | a visit to mawlynnong     | 1
-- 133        | BOOND                     | 2
-- 134        | MY BROTHER ON WHEELCHAIR  | 3
-- 135        | THE COCOON                | 4
-- 30         | Chapter 1: Introduction to English | 5
-- 137        | RED FLOWER                | 5  <-- NEW!

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. This script is safe to run - it only inserts 1 chapter
-- 2. No dependent data (MCQs, videos, notes) needs to be synced
-- 3. After running this, the live Veeru app will show the chapter
-- 4. You can add MCQs/videos/notes through the admin panel after this
-- ============================================================================
