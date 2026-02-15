-- ============================================================================
-- Database Cleanup Script: Remove Duplicate Classes and Subjects
-- ============================================================================
-- Purpose: Fix the issue where CBSE Class 3 English shows incorrect chapter count
--          due to duplicate class and subject entries
--
-- Issue: 
--   - 3 duplicate "Class 3" entries (class_id: 3, 19, 29)
--   - 2 duplicate "English" subjects (subject_id: 13, 33)
--   - Total 5 chapters split across subjects causing display issues
--
-- Solution:
--   1. Migrate chapter from subject_id 33 to subject_id 13
--   2. Delete duplicate subject (subject_id 33)
--   3. Delete duplicate classes (class_id 19, 29)
-- ============================================================================

USE veeru_db;

-- Start transaction for safety
START TRANSACTION;

-- ============================================================================
-- STEP 1: Display current state (for verification)
-- ============================================================================
SELECT '=== CURRENT STATE: Classes ===' AS '';
SELECT class_id, class_name FROM classes 
WHERE class_name LIKE '%Class 3%' OR class_name LIKE '%CLASS 3%'
ORDER BY class_id;

SELECT '=== CURRENT STATE: Subjects ===' AS '';
SELECT s.subject_id, s.subject_name, s.class_id, c.class_name,
       (SELECT COUNT(*) FROM chapters WHERE subject_id = s.subject_id) as chapter_count
FROM subjects s
JOIN classes c ON s.class_id = c.class_id
WHERE s.subject_name LIKE '%English%' AND c.class_name LIKE '%3%'
ORDER BY s.subject_id;

SELECT '=== CURRENT STATE: Chapters ===' AS '';
SELECT chapter_id, chapter_name, subject_id, chapter_order
FROM chapters
WHERE subject_id IN (13, 33)
ORDER BY subject_id, chapter_order, chapter_id;

-- ============================================================================
-- STEP 2: Migrate chapter from subject_id 33 to subject_id 13
-- ============================================================================
SELECT '=== MIGRATING CHAPTER ===' AS '';

-- Update the chapter to belong to subject_id 13 instead of 33
UPDATE chapters
SET subject_id = 13,
    chapter_order = 5
WHERE chapter_id = 30 AND subject_id = 33;

SELECT CONCAT('Migrated chapter_id 30 to subject_id 13') AS 'Migration Status';

-- ============================================================================
-- STEP 3: Delete duplicate subject (subject_id 33)
-- ============================================================================
SELECT '=== DELETING DUPLICATE SUBJECT ===' AS '';

DELETE FROM subjects WHERE subject_id = 33;

SELECT CONCAT('Deleted subject_id 33 (English from class_id 19)') AS 'Deletion Status';

-- ============================================================================
-- STEP 4: Delete duplicate classes (class_id 19 and 29)
-- ============================================================================
SELECT '=== DELETING DUPLICATE CLASSES ===' AS '';

-- Delete class_id 19 (had the duplicate English subject)
DELETE FROM classes WHERE class_id = 19;
SELECT CONCAT('Deleted class_id 19 (CLASS 3)') AS 'Deletion Status';

-- Delete class_id 29 (empty duplicate)
DELETE FROM classes WHERE class_id = 29;
SELECT CONCAT('Deleted class_id 29 (CLASS 3)') AS 'Deletion Status';

-- ============================================================================
-- STEP 5: Verify final state
-- ============================================================================
SELECT '=== FINAL STATE: Classes ===' AS '';
SELECT class_id, class_name FROM classes 
WHERE class_name LIKE '%Class 3%' OR class_name LIKE '%CLASS 3%'
ORDER BY class_id;

SELECT '=== FINAL STATE: Subjects ===' AS '';
SELECT s.subject_id, s.subject_name, s.class_id, c.class_name,
       (SELECT COUNT(*) FROM chapters WHERE subject_id = s.subject_id) as chapter_count
FROM subjects s
JOIN classes c ON s.class_id = c.class_id
WHERE s.subject_name LIKE '%English%' AND c.class_name LIKE '%3%'
ORDER BY s.subject_id;

SELECT '=== FINAL STATE: All Chapters for subject_id 13 ===' AS '';
SELECT chapter_id, chapter_name, subject_id, chapter_order
FROM chapters
WHERE subject_id = 13
ORDER BY chapter_order, chapter_id;

-- ============================================================================
-- STEP 6: Validation checks
-- ============================================================================
SELECT '=== VALIDATION CHECKS ===' AS '';

-- Check 1: Should have exactly 1 "Class 3" entry
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ PASS: Exactly 1 Class 3 entry'
        ELSE '✗ FAIL: Multiple Class 3 entries still exist'
    END AS 'Check 1: Unique Class'
FROM classes
WHERE class_name LIKE '%Class 3%' OR class_name LIKE '%CLASS 3%';

-- Check 2: Should have exactly 1 English subject for Class 3
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ PASS: Exactly 1 English subject for Class 3'
        ELSE '✗ FAIL: Multiple English subjects still exist'
    END AS 'Check 2: Unique Subject'
FROM subjects s
JOIN classes c ON s.class_id = c.class_id
WHERE s.subject_name LIKE '%English%' AND c.class_name LIKE '%3%';

-- Check 3: Subject 13 should have exactly 5 chapters
SELECT 
    CASE 
        WHEN COUNT(*) = 5 THEN '✓ PASS: Subject 13 has 5 chapters'
        ELSE CONCAT('✗ FAIL: Subject 13 has ', COUNT(*), ' chapters (expected 5)')
    END AS 'Check 3: Chapter Count'
FROM chapters
WHERE subject_id = 13;

-- Check 4: Subject 33 should not exist
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ PASS: Subject 33 deleted successfully'
        ELSE '✗ FAIL: Subject 33 still exists'
    END AS 'Check 4: Duplicate Subject Removed'
FROM subjects
WHERE subject_id = 33;

-- ============================================================================
-- COMMIT or ROLLBACK
-- ============================================================================
-- If all validation checks pass, commit the transaction
-- If any check fails, you can manually ROLLBACK

SELECT '=== TRANSACTION READY TO COMMIT ===' AS '';
SELECT 'Review the validation checks above. If all pass, the changes will be committed.' AS 'Instructions';
SELECT 'If any check failed, run: ROLLBACK; to undo all changes.' AS 'Rollback Option';

-- Commit the transaction
COMMIT;

SELECT '=== CLEANUP COMPLETED SUCCESSFULLY ===' AS '';
